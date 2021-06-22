<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Exception;
use App\Models\token;
use Carbon\Carbon;

class CrmController extends Controller
{

    public function curl(array $data){
        $curl = curl_init();
        foreach ($data as $curlopt=>$value){
            curl_setopt($curl,$curlopt,$value);
        }
        $json_response = curl_exec($curl); //Инициируем запрос к API и сохраняем ответ в переменную
        $code          = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $response_data = [
            'json_response'=>$json_response,
            'code'         =>$code,
        ];
        curl_close($curl);
        return $response_data;
    }


    public function auth(Request $request, int $auth_type){
        $clientId     = config('api.api_credentials.client_id'); // id интеграции
        $clientSecret = config('api.api_credentials.client_secret'); // секретный ключ интеграции
        $redirectUri  = config('api.api_credentials.redirect_url'); // домен сайта интеграции
        $subdomain    = config('api.api_credentials.subdomain');; //Поддомен нужного аккаунта
        $link         = 'https://' . $subdomain . '.amocrm.ru/oauth2/access_token'; //Формируем URL для запроса

        $errors = [
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            500 => 'Internal server error',
            502 => 'Bad gateway',
            503 => 'Service unavailable',
        ];

        switch ($auth_type){
            case 1://авторизация с получением кода авторизации - использование кнопки amoCrm
                $auth_code = $request->code; // код авторизации интеграции
                $data = [
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type'    => 'authorization_code',
                    'code'          => $auth_code,
                    'redirect_uri'  => $redirectUri,
                ];
                break;
            case 2://авторизация с использованием Refresh Token
                $data = [
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => token::orderBy('created_at', 'DESC')->first()->refresh_token,
                    'redirect_uri'  => $redirectUri,
                ];
                break;
        }

        //Выполняем запрос
        $response_data = $this->curl([
            CURLOPT_RETURNTRANSFER =>true,
            CURLOPT_USERAGENT      =>'amoCRM-oAuth-client/2.0',
            CURLOPT_URL            =>$link,
            CURLOPT_HTTPHEADER     =>['Content-Type:application/json'],
            CURLOPT_HEADER         =>false,
            CURLOPT_CUSTOMREQUEST  =>'POST',
            CURLOPT_POSTFIELDS     =>json_encode($data),
            CURLOPT_SSL_VERIFYPEER =>1,
            CURLOPT_SSL_VERIFYHOST =>2,
        ]);

        $json_response = $response_data['json_response'];
        $code          = $response_data['code'];

        //Обработка ошибок авторизации
        if ($code < 200 || $code > 204) {
            throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undefined error', $code);
        }else{
            //Обработка ответа API
            $response      = json_decode($json_response,true);
            $refresh_token = $response['refresh_token']; //Refresh токен
            $access_token  = $response['access_token']; //Access токен
            $token_type    = $response['token_type']; //Тип токена
            $expires_in    = $response['expires_in']; //Через сколько действие токена истекает

            if($auth_type == 1){//если авторизация по коду - добавляем новую запись в БД
                $token = new token([
                    'refresh_token'=>$refresh_token,
                    'access_token' =>$access_token,
                    'token_type'   =>$token_type,
                    'expires_in'   =>$expires_in,
                ]);
            }else{//если авторизация по токену - обновляем последнюю запись
                $token = token::orderBy('created_at', 'DESC')->first();
                $token->access_token = $access_token;
                $token->refresh_token= $refresh_token;
                $token->token_type   = $token_type;
                $token->expires_in   = $expires_in;
            }
            $token->save();
        }

        return $code;
    }


    public function get_tokens(){
        $token_model  = token::orderBy('created_at', 'DESC')->first();
        $access_token = $token_model->access_token;
        $refresh_token= $token_model->refresh_token;
        $token_type   = $token_model->token_type;
        $expires_in   = $token_model->expires_in;
        $tokens = [
            'refresh_token'=>$refresh_token,
            'access_token' =>$access_token,
            'token_type'   =>$token_type,
            'expires_in'   =>$expires_in,
        ];
        return $tokens;
    }

    public function add_contact(Request $request){
        //Токены
        $tokens       = $this->get_tokens();
        $access_token = $tokens['access_token'];
        $subdomain    = config('api.api_credentials.subdomain');
        $link         ='https://'.$subdomain.'.amocrm.ru/private/api/v2/json/contacts/set';

        //заголовки
        $headers = [
            "Content-Type: application/json",
            'Authorization: Bearer ' . $access_token
        ];

        //ДОБАВЛЕНИЕ КОНТАКТА
        $contact_data = [
            384867 =>[$request->name,''],
            383857 =>[$request->surname,''],
            346613 =>[$request->phone,'MOB'],
            346615 => [$request->email,'WORK'],
            384247 => [$request->age,''],
            384245 =>[($request->sex == 1)?'М':'Ж','']
        ];

        $api_contact['first_name']    = $contact_data[384867][0];
        $api_contact['last_name']     = $contact_data[383857][0];
        $api_contact['custom_fields'] = [];

        foreach ($contact_data as $id=>$value){
            $custom_field = [
                'id'=>$id,
                'values'=>[
                    [
                        'value'=>$value[0],
                        'enum' =>$value[1],
                    ]
                ],
                ];
            array_push($api_contact['custom_fields'],$custom_field);
        }
        $set['request']['contacts']['add'][]=$api_contact;

        //Выполняем запрос
        $response_data = $this->curl([
            CURLOPT_RETURNTRANSFER =>true,
            CURLOPT_USERAGENT      =>'amoCRM-oAuth-client/2.0',
            CURLOPT_URL            =>$link,
            CURLOPT_HTTPHEADER     =>$headers,
            CURLOPT_HEADER         =>false,
            CURLOPT_CUSTOMREQUEST  =>'POST',
            CURLOPT_POSTFIELDS     =>json_encode($set),
            CURLOPT_SSL_VERIFYPEER =>1,
            CURLOPT_SSL_VERIFYHOST =>2,
        ]);

        $json_response = $response_data['json_response'];
        $code          = $response_data['code'];
        $contact_id    = json_decode($json_response,true)["response"]["contacts"]["add"]["0"]["id"];
        return $contact_id;
    }

    public function add_lead($contact_id){
        //Токены
        $tokens       = $this->get_tokens();
        $access_token = $tokens['access_token'];
        $subdomain    = config('api.api_credentials.subdomain');
        $link         = 'https://'.$subdomain.'.amocrm.ru/private/api/v2/json/leads/set';

        //заголовки
        $headers = [
            "Content-Type: application/json",
            'Authorization: Bearer ' . $access_token
        ];


        //Параметры сделки
        $lead = [
            'name'               => 'Cделка с контактом '.$contact_id,
            'price'              => '10000',
            'custom_fields'      => [],
            'linked_contacts_id' => array(0=>$contact_id),
        ];
        $api_lead['request']['leads']['add'][0]=$lead;

        //Выполняем запрос
        $response_data = $this->curl([
            CURLOPT_RETURNTRANSFER =>true,
            CURLOPT_USERAGENT      =>'amoCRM-oAuth-client/2.0',
            CURLOPT_URL            =>$link,
            CURLOPT_HTTPHEADER     =>$headers,
            CURLOPT_HEADER         =>false,
            CURLOPT_CUSTOMREQUEST  =>'POST',
            CURLOPT_POSTFIELDS     =>json_encode($api_lead),
            CURLOPT_SSL_VERIFYPEER =>1,
            CURLOPT_SSL_VERIFYHOST =>2,
        ]);

        $json_response = $response_data['json_response'];
        $code          = $response_data['code'];
        $lead_id    = json_decode($json_response,true)["response"]["leads"]["add"]["0"]["id"];
        return $lead_id;
    }

    public function add_task($lead_id){
        //Токены
        $tokens       = $this->get_tokens();
        $access_token = $tokens['access_token'];
        $subdomain    = config('api.api_credentials.subdomain');
        $link='https://'.$subdomain.'.amocrm.ru/private/api/v2/json/tasks/set';

        //заголовки
        $headers = [
            "Content-Type: application/json",
            'Authorization: Bearer ' . $access_token
        ];

        //Описание задачи
        $tasks['request']['tasks']['add'][0] = [
            'element_id'         => $lead_id, #ID of the lead
            'element_type'       => 2, #Show that this is a lead, not a contact
            'task_type'          => 1, #Call
            'text'               => 'Задача, закрепленная за сделкой '.$lead_id,
            'responsible_user_id'=> 2976497,
            'complete_till'      => time()+345600,//unix-время + кол-во секунд в 4х днях
        ];

        //Выполняем запрос
        $response_data = $this->curl([
            CURLOPT_RETURNTRANSFER =>true,
            CURLOPT_USERAGENT      =>'amoCRM-oAuth-client/2.0',
            CURLOPT_URL            =>$link,
            CURLOPT_HTTPHEADER     =>$headers,
            CURLOPT_HEADER         =>false,
            CURLOPT_CUSTOMREQUEST  =>'POST',
            CURLOPT_POSTFIELDS     =>json_encode($tasks),
            CURLOPT_SSL_VERIFYPEER =>1,
            CURLOPT_SSL_VERIFYHOST =>2,
        ]);

        $json_response = $response_data['json_response'];
        $code          = $response_data['code'];
        $task_id    = json_decode($json_response,true)["response"]["tasks"]["add"]["0"]["id"];
        return $task_id;

    }

    public function add_products($lead_id){
        //Токены
        $tokens       = $this->get_tokens();
        $access_token = $tokens['access_token'];
        $subdomain    = config('api.api_credentials.subdomain');
        $link = 'https://'.$subdomain.'.amocrm.ru/api/v2/catalog_elements';
        $link2= 'https://'.$subdomain.'.amocrm.ru/api/v4/leads/'.$lead_id.'/link';

        //заголовки
        $headers = [
            "Content-Type: application/json",
            'Authorization: Bearer ' . $access_token
        ];

        //Сначала добавляется новый список "Товары" на сайте, потом можно добавлять в него товары через API
        //Описание товаров
        $catalog_elements['add'] = [
            [
                'catalog_id' => 3909,
                'name'=>'product 1',
                'price'=>'500',
            ],
            [
                'catalog_id' => 3909,
                'name'=>'product 2',
                'price'=>'500'
            ]
        ];

        //Выполняем запрос на добавление товаров
        $response_data = $this->curl([
            CURLOPT_RETURNTRANSFER =>true,
            CURLOPT_USERAGENT      =>'amoCRM-oAuth-client/2.0',
            CURLOPT_URL            =>$link,
            CURLOPT_HTTPHEADER     =>$headers,
            CURLOPT_HEADER         =>false,
            CURLOPT_CUSTOMREQUEST  =>'POST',
            CURLOPT_POSTFIELDS     =>json_encode($catalog_elements),
            CURLOPT_SSL_VERIFYPEER =>1,
            CURLOPT_SSL_VERIFYHOST =>2,
        ]);

        $json_response = $response_data['json_response'];
        $code          = $response_data['code'];
        $products      = json_decode($json_response,true)[ '_embedded' ] [ 'items' ];
        $products_id   = [];
        foreach ($products as $product){
            array_push($products_id,$product['id']);
        }
        return $products_id;
    }

    public function link_products($lead_id,$products_id){
        //Токены
        $tokens       = $this->get_tokens();
        $access_token = $tokens['access_token'];
        $subdomain    = config('api.api_credentials.subdomain');
        $link= 'https://'.$subdomain.'.amocrm.ru/api/v4/leads/'.$lead_id.'/link';

        //заголовки
        $headers = [
            "Content-Type: application/json",
            'Authorization: Bearer ' . $access_token
        ];

        $linked_products=[];
        foreach ($products_id as $product_id){
            $linked_arr = [
                'to_entity_id'  => $product_id,
                'to_entity_type'=> 'catalog_elements',
                "metadata"      => ['catalog_id'=> 3909],
            ];
            array_push($linked_products,$linked_arr);
        }

        $response_data = $this->curl([
            CURLOPT_RETURNTRANSFER =>true,
            CURLOPT_USERAGENT      =>'amoCRM-oAuth-client/2.0',
            CURLOPT_URL            =>$link,
            CURLOPT_HTTPHEADER     =>$headers,
            CURLOPT_HEADER         =>false,
            CURLOPT_CUSTOMREQUEST  =>'POST',
            CURLOPT_POSTFIELDS     =>json_encode($linked_products),
            CURLOPT_SSL_VERIFYPEER =>1,
            CURLOPT_SSL_VERIFYHOST =>2,
        ]);

        return $response_data['json_response'];
    }

    public function getEntitiesById($main_entity_name,$main_entity_id){
        //Токены
        $tokens       = $this->get_tokens();
        $access_token = $tokens['access_token'];
        $subdomain    = config('api.api_credentials.subdomain');
        $link= 'https://'.$subdomain.'.amocrm.ru/api/v4/'.$main_entity_name.'/'.$main_entity_id.'/links';

        //заголовки
        $headers = [
            "Content-Type: application/json",
            'Authorization: Bearer ' . $access_token
        ];

        $data = $this->curl([
            CURLOPT_RETURNTRANSFER =>true,
            CURLOPT_USERAGENT      =>'amoCRM-oAuth-client/2.0',
            CURLOPT_URL            =>$link,
            CURLOPT_HTTPHEADER     =>$headers,
            CURLOPT_HEADER         =>false,
            CURLOPT_CUSTOMREQUEST  =>'GET',
            CURLOPT_SSL_VERIFYPEER =>1,
            CURLOPT_SSL_VERIFYHOST =>2,
        ]);

        return json_decode($data['json_response'],true)['_embedded']['links'];
    }

    public function double_control($phone){
        //Токены
        $tokens       = $this->get_tokens();
        $access_token = $tokens['access_token'];
        $subdomain    = config('api.api_credentials.subdomain');

        //заголовки
        $headers = [
            "Content-Type: application/json",
            'Authorization: Bearer ' . $access_token
        ];

        $contacts_data = $this->curl([
            CURLOPT_RETURNTRANSFER =>true,
            CURLOPT_USERAGENT      =>'amoCRM-oAuth-client/2.0',
            CURLOPT_URL            =>'https://'.$subdomain.'.amocrm.ru/api/v4/contacts',
            CURLOPT_HTTPHEADER     =>$headers,
            CURLOPT_HEADER         =>false,
            CURLOPT_CUSTOMREQUEST  =>'GET',
            CURLOPT_SSL_VERIFYPEER =>1,
            CURLOPT_SSL_VERIFYHOST =>2,
        ]);

        $contacts = json_decode($contacts_data['json_response'],true);

        $action = [0,''];

        if (is_array($contacts)){
            foreach ($contacts['_embedded']['contacts'] as $contact){
                foreach ($contact['custom_fields_values'] as $custom_field){
                    if ($custom_field['field_id'] == '346613'){
                        if ($custom_field['values'][0]['value']==$phone){
                            $id = $contact['id'];
                        }
                    }
                }
            }

            if (isset($id)){ // Если совпадение по номеру телефона, получаем связанную сделку
                $lead_id = $this->getEntitiesById('contacts',$id)[0]['to_entity_id'];
                $lead = $this->curl([
                    CURLOPT_RETURNTRANSFER =>true,
                    CURLOPT_USERAGENT      =>'amoCRM-oAuth-client/2.0',
                    CURLOPT_URL            =>'https://'.$subdomain.'.amocrm.ru/api/v4/leads/'.$lead_id,
                    CURLOPT_HTTPHEADER     =>$headers,
                    CURLOPT_HEADER         =>false,
                    CURLOPT_CUSTOMREQUEST  =>'GET',
                    CURLOPT_SSL_VERIFYPEER =>1,
                    CURLOPT_SSL_VERIFYHOST =>2,
                ]);
                $status = json_decode($lead['json_response'],true)['status_id'];
                if ($status == 142){
                    $action = [1,$id];
                }else{
                    $action = [2,''];
                }
            }
        }

        return $action;
    }

    public function add_customer($contact_id){
        //Токены
        $tokens       = $this->get_tokens();
        $access_token = $tokens['access_token'];
        $subdomain    = config('api.api_credentials.subdomain');
        $link='https://'.$subdomain.'.amocrm.ru/api/v4/customers';

        //заголовки
        $headers = [
            "Content-Type: application/json",
            'Authorization: Bearer ' . $access_token
        ];

        //Параметры покупателя
        $customer_data = [
            [
                'name'=>'Customer 1',
            ]
        ];

        //Выполняем запрос на добавление товаров
        $response_data = $this->curl([
            CURLOPT_RETURNTRANSFER =>true,
            CURLOPT_USERAGENT      =>'amoCRM-oAuth-client/2.0',
            CURLOPT_URL            =>$link,
            CURLOPT_HTTPHEADER     =>$headers,
            CURLOPT_HEADER         =>false,
            CURLOPT_CUSTOMREQUEST  =>'POST',
            CURLOPT_POSTFIELDS     =>json_encode($customer_data),
            CURLOPT_SSL_VERIFYPEER =>1,
            CURLOPT_SSL_VERIFYHOST =>2,
        ]);

        $json_response = $response_data['json_response'];
        $code          = $response_data['code'];

        return json_decode($json_response,true)['_embedded']['customers'][0]['id'];
    }

    public function link_customer_to_contact($customer_id,$contact_id){
        //Токены
        $tokens       = $this->get_tokens();
        $access_token = $tokens['access_token'];
        $subdomain    = config('api.api_credentials.subdomain');
        $link= 'https://'.$subdomain.'.amocrm.ru/api/v4/contacts/'.$contact_id.'/link';

        //заголовки
        $headers = [
            "Content-Type: application/json",
            'Authorization: Bearer ' . $access_token
        ];

        $linked[0]    = [
            'to_entity_id'  => $customer_id,
            'to_entity_type'=> 'customers',
            "metadata"      => [],
        ];

        //Выполняем запрос на добавление товаров
        $response_data = $this->curl([
            CURLOPT_RETURNTRANSFER =>true,
            CURLOPT_USERAGENT      =>'amoCRM-oAuth-client/2.0',
            CURLOPT_URL            =>$link,
            CURLOPT_HTTPHEADER     =>$headers,
            CURLOPT_HEADER         =>false,
            CURLOPT_CUSTOMREQUEST  =>'POST',
            CURLOPT_POSTFIELDS     =>json_encode($linked),
            CURLOPT_SSL_VERIFYPEER =>1,
            CURLOPT_SSL_VERIFYHOST =>2,
        ]);

        $json_response = $response_data['json_response'];
        $code          = $response_data['code'];

        return $json_response;

    }

    public function index(Request $request){

        //Попытка авторизации: 90 дней действует Refresh Token, 1 день действует Access Token
        //Проверяем, сколько дней прошло с момента обновления последний записи - столько дней назад получен последний access token
        $last_refresh_token = token::orderBy('created_at', 'DESC')->first();
        $update             = $last_refresh_token->updated_at;
        $days_left          = ceil((Carbon::now()->getTimestamp() - Carbon::parse($update)->getTimestamp())/86400);
        if ($days_left >= 2){//если прошло больше суток (ceil округляет до 2), получаем новую пару Refresh token -Access token
            $auth_code = $this->auth($request,2);
        }elseif($days_left >= 90){//если не было активности больше 3 месяцев, проходим авторизацию заново
            $auth_code = $this->auth($request,1);
        }elseif (!$last_refresh_token){
            $auth_code = $this->auth($request,1);
        }

        $action = $this->double_control($request->phone);

        if ($action[0] == 0){//совпадений не найдено, добавляем контакт, сделку, задачу и товары
            //Добавляем контакт, получаем его ID
            $contact_id=$this->add_contact($request);
            //Прикрепляем к контакту сделку
            $lead_id = $this->add_lead($contact_id);
            //Прикрепляем к сделке задачу
            $task_id = $this->add_task($lead_id);
            //Добавляем товары в список
            $products_id = $this->add_products($lead_id);
            //Прикрепляем товары к сделке
            $resp = $this->link_products($lead_id, $products_id);
        }elseif ($action[0] == 1){//совпадения найдены и сделка в статусе 142
            $contact_id = $action[1];
            $customer_id = $this->add_customer($contact_id);
            $json_customer = $this->link_customer_to_contact($customer_id,$contact_id);
        }


        return '';
    }
}
