@extends('layouts.app')
@section('title')Welcome!@endsection
@section('content')
    @if($show_button)
        <div class="row justify-content-center mt-3">
            <div class="w-25">
                <script
                    class="amocrm_oauth"
                    charset="utf-8"
                    data-client-id="{{config('api.api_credentials.client_id')}}"
                    data-title="Авторизация в AmoCRM"
                    data-compact="false"
                    data-class-name="className"
                    data-color="default"
                    data-state="state"
                    data-error-callback="functionName"
                    data-mode="popup"
                    src="https://www.amocrm.ru/auth/button.min.js"
                ></script>
            </div>
        </div>
    @endif
    <div class="row justify-content-center mt-3">
        <form class="w-25 mt-3" method="POST" id="contactform" name="contactform">
            @csrf
            <input type="hidden" name="code" id="code">

            <div class="form-group row">
                <label for="name" class="col-sm-2 col-form-label">Имя</label>
                <div class="col-sm-10">
                    <input class="form-control" id="name" name="name" placeholder="Введите имя">
                </div>
            </div>

            <div class="form-group row mt-2">
                <label for="surname" class="col-sm-2 col-form-label">Фамилия</label>
                <div class="col-sm-10">
                    <input class="form-control" id="surname" name="surname" placeholder="Введите фамилию">
                </div>
            </div>

            <div class="form-group row mt-2">
                <label for="age" class="col-sm-2 col-form-label">Возраст</label>
                <div class="col-sm-10">
                    <input class="form-control" id="age" name="age" placeholder="Сколько Вам лет?">
                </div>
            </div>

            <div class="form-group row mt-2">
                <label for="sex" class="col-sm-2 col-form-label">Пол</label>
                <div class="col-sm-10">
                    <select class="custom-select w-100 h-100" id="sex" name="sex">
                        <option selected>Выберете пол</option>
                        <option value="1">Мужской</option>
                        <option value="2">Женский</option>
                    </select>
                </div>
            </div>

            <div class="form-group row mt-2">
                <label for="phone" class="col-sm-2 col-form-label">Телефон</label>
                <div class="col-sm-10">
                    <input class="form-control" id="phone" name="phone" placeholder="Телефон">
                </div>
            </div>

            <div class="form-group row mt-2">
                <label for="email" class="col-sm-2 col-form-label">Email</label>
                <div class="col-sm-10">
                    <input type="email" class="form-control" id="email" name="email" placeholder="Email">
                </div>
            </div>

            <div class="form-group row">
                <div class="col-sm-10 offset-sm-2">
                    <button type="submit" class="btn btn-primary mt-4 ml-0">Отправить</button>
                </div>
            </div>

        </form>
    </div>
@endsection
@section('after_scripts')
    <script>
        $(document).ready(function () {
            $('#contactform').on('submit', function (e) {
                e.preventDefault();
                document.forms.contactform.code.value = '{{isset($_GET['code'])?$_GET['code']:''}}';
                $.ajax({
                    type: 'POST',
                    url: '/sendform',
                    data: $('#contactform').serialize(),
                    success: function (data) {
                        console.log(data)
                    },
                    error: function () {

                    }
                });
            });
        });
    </script>
@endsection
