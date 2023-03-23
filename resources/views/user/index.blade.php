@extends('layouts.app')

@section('content')

<div class='container'>
    <div class='row text-center justify-content-center'>
        <div class='col-12 col-sm-12 col-md-4 col-lg-4 col-xl-4  custom-border-col-right'>
            <div class="mb-3">
                <label for="phone" class="form-label">Номер телефона</label>
                <input type="text" class="form-control change-attribute" id="phone" placeholder='+7 (999) 999-99-99' value='{{ $model->phone }}'>
                <div id="phoneHelp" class="form-text">*обязательно</div>
            </div>
        </div>
        <div class='col-12 col-sm-12 col-md-4 col-lg-4 col-xl-4  custom-border-col-right'>
            <div class="mb-3">
                <label for="phone" class="form-label">Город</label>
                <input type="text" class="form-control change-attribute" id="city" placeholder='' value='{{ $model->city }}'>
                <div id="phoneHelp" class="form-text">*обязательно</div>
            </div>
        </div>
        <div class='col-12 col-sm-12 col-md-4 col-lg-4 col-xl-4 '>
            <div class="mb-3">
                <label for="phone" class="form-label">Возраст</label>
                <input type="number" class="form-control change-attribute" id="age" placeholder='' value='{{ $model->age }}'>
                <div id="phoneHelp" class="form-text">*обязательно</div>
            </div>
        </div>
    </div>
    <div class='row text-center justify-content-center'>
        <div class='col-12 col-sm-12 col-md-4 col-lg-4 col-xl-4  custom-border-col-right'>
            <div class="mb-3">
                <label for="phone" class="form-label">Способы оплаты</label>
                <textarea class="form-control change-attribute" rows="2" placeholder='Способы оплаты' id="payment_method" >{{ $model->payment_method }}</textarea>
                <div id="phoneHelp" class="form-text">*обязательно</div>
            </div>
        </div>
        <div class='col-12 col-sm-12 col-md-4 col-lg-4 col-xl-4  custom-border-col-right'>
            <div class="mb-3">
                <label for="phone" class="form-label">Род деятельности</label>
                <input type="text" class="form-control change-attribute" id="work" placeholder='' value='{{ $model->work }}'>
                <div id="phoneHelp" class="form-text">*обязательно</div>
            </div>
        </div>
        <div class='col-12 col-sm-12 col-md-4 col-lg-4 col-xl-4 '>
            <div class="mb-3">
                <label for="phone" class="form-label">Пол</label>

                <select class="form-select change-attribute" aria-label="Пол" id="floor">
                    @if (empty($model->floor) == true)
                        <option value="" selected>Не выбрано</option>
                        <option value="М">М</option>
                        <option value="Ж">Ж</option>
                    @elseif ($model->floor = 'М') 
                        <option value="">Не выбрано</option>
                        <option value="М" selected>М</option>
                        <option value="Ж">Ж</option>
                    @elseif ($model->floor = 'Ж')
                        <option value="">Не выбрано</option>
                        <option value="М">М</option>
                        <option value="Ж" selected>Ж</option>
                    @endif

                </select>
                <div id="phoneHelp" class="form-text">*обязательно</div>
            </div>
        </div>
        <div class='col-12 col-sm-12 col-md-4 col-lg-4 col-xl-4'>
            <div class="mb-3">
                <label for="exampleInputEmail1" class="form-label">Уникальный номер</label>
                <input type="text" class="form-control" id='uniqueNumber' value='{{ $service->getUrl() }}' disabled>
                <div id="emailHelp" class="form-text">Ваш уникальный номер</div>
            </div>
        </div>
    </div>

    <hr>

    <div class='row text-center justify-content-center'>
        <div class='col-12 col-sm-12 col-md-12 col-lg-12 col-xl-12'>
            @if ($model->time_active != null)
                <label class="form-label">Ссылка активирована</label>
            @else
                <label class="form-label hidden" id='label-active-url'>Ссылка активирована</label>
                <button type="button" class="btn btn-primary" style='max-width:130px;' id='button-active'>Активировать</button>
            @endif
        </div>
    </div>

    <section id='section-payment' 
        @if ($model->time_active == null)
            class='hidden'
        @endif 
     >
        <hr>
        <div class='row text-center justify-content-center bg-light border-payment'>
            <h2>Оплата</h2>
            <div class="p-3 mb-2 bg-warning text-dark">
                <span>
                    Пожалуйста, оплатите <b>{{ $serviceConfigurations->getSum() }}</b> рублей по номеру телефона: 
                </span>
            </div>
            <div class="p-3 mb-2 bg-info text-body">
                <span>
                    <b>{{ $service->getPhoneParentByPayment() }}</b>
                </span>
            </div>
            <div class="p-3 mb-2 bg-info text-body">
                <span>
                    {{ $service->getPaymentMethodParentByPayment() }}
                </span>
            </div>
            <div>
                @if ($model->time_payment == null)
                    <button type="button" class="btn btn-success" style='max-width:130px;' id='button-payment'>Оплатил</button>
                @else
                    <hr>
                    <label class="form-label " id='label-active-url'>Оплачено ({{ $model->time_payment }})</label>
                    <hr>
                @endif
            </div>
        </div>
    </section>

    
    <section id='table-url' 
        @if ($model->time_payment == null)
            class='hidden'
        @endif 
    >
        <hr>
        <div class='row table-responsive-sm'>
            <table id="jquery-datatable-example-no-configuration" class="display table-sm table" style="width:100%">
                <thead>
                    <tr>
                        <th>Дата создания</th>
                        <th>Дата первого перехода</th>
                        <th>Дата активации</th>
                        <th>Дата оплаты</th>
                        <th>Уникальный номер</th>
                        <th>Копирование</th>
                        <th>Поделиться</th>
                        <th>Комментарий</th>
                        <th>Сохранить</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($service->getChildren() as $item)
                        <tr id='{{ $item->url }}'
                            class='{{ $item->getClassColor() }}'
                        >
                            <td>{{ $item->created_at }}</td>
                            <td>{{ $item->time_first_open_url }}</td>
                            <td>{{ $item->time_active }}</td>
                            <td>{{ $item->time_payment }}</td>
                            <td>{{ $item->url }}</td>
                            <th><button type="button" class="btn btn-primary copy" data-url='{{ url("/unique-id/" . $item->url) }}'>Копировать</button></th>
                            <th>
                                <div class="ya-share2" 
                                    data-curtain 
                                    data-shape="round" 
                                    data-limit="0" 
                                    data-more-button-type="long"
                                    data-url="{{ url("/unique-id/" . $item->url) }}"
                                    data-services="vkontakte,odnoklassniki,telegram,twitter,viber,whatsapp"
                                    data-description='Ссылка на личный кабинет'
                                    data-title='Ссылка на личный кабинет'
                                >
                                </div>
                            </th>
                            <th><textarea class="form-control comment" rows="2" placeholder='Комментарий' id='{{ $item->url }}-comment'>{{ $item->comment }}</textarea></th>
                            <th><button type="button" class="btn btn-primary save-change-children-url" data-url='{{ $item->url }}' disabled>Сохранить</button></th>
                        </tr>
                    @endforeach

                </tbody>
            </table>
        </div>
    </section>
</div>

@endsection

@section('after_scripts')
    <script>
        $("#phone").mask("+7 (999) 999-99-99");

        var table = $('#jquery-datatable-example-no-configuration').DataTable({
            "aaSorting": [[ 0, "desc" ]],
            language: {
                "processing": "Подождите...",
                "search": "Поиск:",
                "lengthMenu": "Показать _MENU_ записей",
                "info": "Записи с _START_ до _END_ из _TOTAL_ записей",
                "infoEmpty": "Записи с 0 до 0 из 0 записей",
                "infoFiltered": "(отфильтровано из _MAX_ записей)",
                "loadingRecords": "Загрузка записей...",
                "zeroRecords": "Записи отсутствуют.",
                "emptyTable": "В таблице отсутствуют данные",
                "paginate": {
                    "first": "Первая",
                    "previous": "Предыдущая",
                    "next": "Следующая",
                    "last": "Последняя"
                },
                "aria": {
                    "sortAscending": ": активировать для сортировки столбца по возрастанию",
                    "sortDescending": ": активировать для сортировки столбца по убыванию"
                },
                "select": {
                    "rows": {
                        "_": "Выбрано записей: %d",
                        "1": "Выбрана одна запись"
                    },
                    "cells": {
                        "_": "Выбрано %d ячеек",
                        "1": "Выбрана 1 ячейка "
                    },
                    "columns": {
                        "1": "Выбран 1 столбец ",
                        "_": "Выбрано %d столбцов "
                    }
                },
                "searchBuilder": {
                    "conditions": {
                        "string": {
                            "startsWith": "Начинается с",
                            "contains": "Содержит",
                            "empty": "Пусто",
                            "endsWith": "Заканчивается на",
                            "equals": "Равно",
                            "not": "Не",
                            "notEmpty": "Не пусто",
                            "notContains": "Не содержит",
                            "notStartsWith": "Не начинается на",
                            "notEndsWith": "Не заканчивается на"
                        },
                        "date": {
                            "after": "После",
                            "before": "До",
                            "between": "Между",
                            "empty": "Пусто",
                            "equals": "Равно",
                            "not": "Не",
                            "notBetween": "Не между",
                            "notEmpty": "Не пусто"
                        },
                        "number": {
                            "empty": "Пусто",
                            "equals": "Равно",
                            "gt": "Больше чем",
                            "gte": "Больше, чем равно",
                            "lt": "Меньше чем",
                            "lte": "Меньше, чем равно",
                            "not": "Не",
                            "notEmpty": "Не пусто",
                            "between": "Между",
                            "notBetween": "Не между ними"
                        },
                        "array": {
                            "equals": "Равно",
                            "empty": "Пусто",
                            "contains": "Содержит",
                            "not": "Не равно",
                            "notEmpty": "Не пусто",
                            "without": "Без"
                        }
                    },
                    "data": "Данные",
                    "deleteTitle": "Удалить условие фильтрации",
                    "logicAnd": "И",
                    "logicOr": "Или",
                    "title": {
                        "0": "Конструктор поиска",
                        "_": "Конструктор поиска (%d)"
                    },
                    "value": "Значение",
                    "add": "Добавить условие",
                    "button": {
                        "0": "Конструктор поиска",
                        "_": "Конструктор поиска (%d)"
                    },
                    "clearAll": "Очистить всё",
                    "condition": "Условие",
                    "leftTitle": "Превосходные критерии",
                    "rightTitle": "Критерии отступа"
                },
                "searchPanes": {
                    "clearMessage": "Очистить всё",
                    "collapse": {
                        "0": "Панели поиска",
                        "_": "Панели поиска (%d)"
                    },
                    "count": "{total}",
                    "countFiltered": "{shown} ({total})",
                    "emptyPanes": "Нет панелей поиска",
                    "loadMessage": "Загрузка панелей поиска",
                    "title": "Фильтры активны - %d",
                    "showMessage": "Показать все",
                    "collapseMessage": "Скрыть все"
                },
                "buttons": {
                    "pdf": "PDF",
                    "print": "Печать",
                    "collection": "Коллекция <span class=\"ui-button-icon-primary ui-icon ui-icon-triangle-1-s\"><\/span>",
                    "colvis": "Видимость столбцов",
                    "colvisRestore": "Восстановить видимость",
                    "copy": "Копировать",
                    "copyKeys": "Нажмите ctrl or u2318 + C, чтобы скопировать данные таблицы в буфер обмена.  Для отмены, щелкните по сообщению или нажмите escape.",
                    "copyTitle": "Скопировать в буфер обмена",
                    "csv": "CSV",
                    "excel": "Excel",
                    "pageLength": {
                        "-1": "Показать все строки",
                        "_": "Показать %d строк",
                        "1": "Показать 1 строку"
                    },
                    "removeState": "Удалить",
                    "renameState": "Переименовать",
                    "copySuccess": {
                        "1": "Строка скопирована в буфер обмена",
                        "_": "Скопировано %d строк в буфер обмена"
                    },
                    "createState": "Создать состояние",
                    "removeAllStates": "Удалить все состояния",
                    "savedStates": "Сохраненные состояния",
                    "stateRestore": "Состояние %d",
                    "updateState": "Обновить"
                },
                "decimal": ".",
                "infoThousands": ",",
                "autoFill": {
                    "cancel": "Отменить",
                    "fill": "Заполнить все ячейки <i>%d<i><\/i><\/i>",
                    "fillHorizontal": "Заполнить ячейки по горизонтали",
                    "fillVertical": "Заполнить ячейки по вертикали",
                    "info": "Информация"
                },
                "datetime": {
                    "previous": "Предыдущий",
                    "next": "Следующий",
                    "hours": "Часы",
                    "minutes": "Минуты",
                    "seconds": "Секунды",
                    "unknown": "Неизвестный",
                    "amPm": [
                        "AM",
                        "PM"
                    ],
                    "months": {
                        "0": "Январь",
                        "1": "Февраль",
                        "10": "Ноябрь",
                        "11": "Декабрь",
                        "2": "Март",
                        "3": "Апрель",
                        "4": "Май",
                        "5": "Июнь",
                        "6": "Июль",
                        "7": "Август",
                        "8": "Сентябрь",
                        "9": "Октябрь"
                    },
                    "weekdays": [
                        "Вс",
                        "Пн",
                        "Вт",
                        "Ср",
                        "Чт",
                        "Пт",
                        "Сб"
                    ]
                },
                "editor": {
                    "close": "Закрыть",
                    "create": {
                        "button": "Новый",
                        "title": "Создать новую запись",
                        "submit": "Создать"
                    },
                    "edit": {
                        "button": "Изменить",
                        "title": "Изменить запись",
                        "submit": "Изменить"
                    },
                    "remove": {
                        "button": "Удалить",
                        "title": "Удалить",
                        "submit": "Удалить",
                        "confirm": {
                            "_": "Вы точно хотите удалить %d строк?",
                            "1": "Вы точно хотите удалить 1 строку?"
                        }
                    },
                    "multi": {
                        "restore": "Отменить изменения",
                        "title": "Несколько значений",
                        "noMulti": "Это поле должно редактироватся отдельно, а не как часть групы",
                        "info": "Выбранные элементы содержат разные значения для этого входа.  Чтобы отредактировать и установить для всех элементов этого ввода одинаковое значение, нажмите или коснитесь здесь, в противном случае они сохранят свои индивидуальные значения."
                    },
                    "error": {
                        "system": "Возникла системная ошибка (<a target=\"\\\" rel=\"nofollow\" href=\"\\\">Подробнее<\/a>)."
                    }
                },
                "searchPlaceholder": "Что ищете?",
                "stateRestore": {
                    "creationModal": {
                        "button": "Создать",
                        "search": "Поиск",
                        "columns": {
                            "search": "Поиск по столбцам",
                            "visible": "Видимость столбцов"
                        },
                        "name": "Имя:",
                        "order": "Сортировка",
                        "paging": "Страницы",
                        "scroller": "Позиция прокрутки",
                        "searchBuilder": "Редактор поиска",
                        "select": "Выделение",
                        "title": "Создать новое состояние",
                        "toggleLabel": "Включает:"
                    },
                    "removeJoiner": "и",
                    "removeSubmit": "Удалить",
                    "renameButton": "Переименовать",
                    "duplicateError": "Состояние с таким именем уже существует.",
                    "emptyError": "Имя не может быть пустым.",
                    "emptyStates": "Нет сохраненных состояний",
                    "removeConfirm": "Вы уверены, что хотите удалить %s?",
                    "removeError": "Не удалось удалить состояние.",
                    "removeTitle": "Удалить состояние",
                    "renameLabel": "Новое имя для %s:",
                    "renameTitle": "Переименовать состояние"
                },
                "thousands": " "
            }
        });

        /**
         * Подготовка json для отправки на сервер
         * 
         */
        function preparationData(obj) {
            if (obj.attr('id') == 'phone') {
                return {
                    url: $('#uniqueNumber').val(),
                    phone: $('#phone').val()
                };
            }

            if (obj.attr('id') == 'city') {
                return {
                    url: $('#uniqueNumber').val(),
                    city: $('#city').val()
                };
            }

            if (obj.attr('id') == 'age') {
                return {
                    url: $('#uniqueNumber').val(),
                    age: $('#age').val()
                };
            }

            if (obj.attr('id') == 'work') {
                return {
                    url: $('#uniqueNumber').val(),
                    work: $('#work').val()
                };
            }

            if (obj.attr('id') == 'floor') {
                return {
                    url: $('#uniqueNumber').val(),
                    floor: $('#floor').val()
                };
            }

            if (obj.attr('id') == 'payment_method') {
                return {
                    url: $('#uniqueNumber').val(),
                    payment_method: $('#payment_method').val()
                };
            }
        }

        /**
         * Изменение номера
         */
        function changeAttribute(obj) {
            data = preparationData(obj);

            $.ajax({
                url: '/change-attribute',
                method: 'post',
                dataType: "json",
                data: data,
                success: function(data){
                    if (data.status == 'error') {
                        toastr.error(data.message);
                    }
                    else{
                        toastr.success('Сохранено!');
                    }
                },
                error: function (jqXHR, exception) {
                    if (jqXHR.status === 0) {
                        alert('Not connect. Verify Network.');
                    } else if (jqXHR.status == 404) {
                        alert('Requested page not found (404).');
                    } else if (jqXHR.status == 500) {
                        alert('Internal Server Error (500).');
                    } else if (exception === 'parsererror') {
                        alert('Requested JSON parse failed.');
                    } else if (exception === 'timeout') {
                        alert('Time out error.');
                    } else if (exception === 'abort') {
                        alert('Ajax request aborted.');
                    } else {
                        alert('Uncaught Error. ' + jqXHR.responseText);
                    }
                }
            });
        }

        /**
         * Запрос на генерацию ссылки
         */
         function generateUrlRequest() {
            data = {
                url: $('#uniqueNumber').val(),
            };

            $.ajax({
                url: '/generate-url-for-user',
                method: 'post',
                dataType: "json",
                data: data,
                success: function(data){
                    toastr.success('Ссылки сгенерированы!');
                    $('#button-payment').addClass('hidden')
                    $('#table-url').removeClass('hidden')
                    var length = data.array.length,
                    element = null;
                    for (var i = 0; i < length; i++) {
                        element = data.array[i];
                        date = element[1]['date']

                        var rowNode = table
                            .row.add([ 
                                date.substr(0, date.lastIndexOf('.')),
                                '',
                                '',
                                '',
                                element[0],
                                '<button type="button" class="btn btn-primary copy" data-url="' + document.location.origin + "/unique-id/" + element[0] + '">Копировать</button>', 
                                '<button type="button" class="btn btn-primary">Будет доступно после обновления страницы</button>', 
                                '<textarea class="form-control comment" rows="2" placeholder="Комментарий" id="' + element[0] + '-comment"></textarea>', 
                                '<button type="button" class="btn btn-primary save-change-children-url" disabled>Сохранить</button>'
                            ])
                            .draw(false)
                            .node();

                        $( rowNode )
                            .css('color', 'red')
                            .animate({ color: 'black' });
                        table.order([[0,'desc']]);
                    }
                },
                error: function (jqXHR, exception) {
                    if (jqXHR.status === 0) {
                        alert('Not connect. Verify Network.');
                    } else if (jqXHR.status == 404) {
                        alert('Requested page not found (404).');
                    } else if (jqXHR.status == 500) {
                        alert('Internal Server Error (500).');
                    } else if (exception === 'parsererror') {
                        alert('Requested JSON parse failed.');
                    } else if (exception === 'timeout') {
                        alert('Time out error.');
                    } else if (exception === 'abort') {
                        alert('Ajax request aborted.');
                    } else {
                        alert('Uncaught Error. ' + jqXHR.responseText);
                    }
                }
            });
        }

        function validateAttribute() {
            if ($('#phone').val() == '') {
                toastr.warning('Необходимо заполнить номер телефона!');
                return false;
            }

            if ($('#city').val() == '') {
                toastr.warning('Необходимо заполнить город!');
                return false;
            }

            if ($('#age').val() == '') {
                toastr.warning('Необходимо заполнить возраст!');
                return false;
            }

            if ($('#work').val() == '') {
                toastr.warning('Необходимо заполнить род деятельности!');
                return false;
            }

            if ($('#floor').val() == '') {
                toastr.warning('Необходимо заполнить пол!');
                return false;
            }

            if ($('#payment_method').val() == '') {
                toastr.warning('Необходимо метод оплаты!');
                return false;
            }

            return true;
        }

        /**
         * Активация ссылки
         */
        function activeUrlRequest() {
            if (validateAttribute() == false) {
                return;
            }

            data = {
                url: $('#uniqueNumber').val()
            };

            $.ajax({
                url: '/active-url',
                method: 'post',
                dataType: "json",
                data: data,
                success: function(data){
                    toastr.success('Ссылка активирована!');
                    $('#section-payment').removeClass('hidden')
                    $('#label-active-url').removeClass('hidden')
                    $('#button-active').addClass('hidden')
                },
                error: function (jqXHR, exception) {
                    if (jqXHR.status === 0) {
                        alert('Not connect. Verify Network.');
                    } else if (jqXHR.status == 404) {
                        alert('Requested page not found (404).');
                    } else if (jqXHR.status == 500) {
                        alert('Internal Server Error (500).');
                    } else if (exception === 'parsererror') {
                        alert('Requested JSON parse failed.');
                    } else if (exception === 'timeout') {
                        alert('Time out error.');
                    } else if (exception === 'abort') {
                        alert('Ajax request aborted.');
                    } else {
                        alert('Uncaught Error. ' + jqXHR.responseText);
                    }
                }
            });
        }

        /**
         * Копирование в буфер обмена
         * 
         */
         function copyToClipboard(element) {
            var $temp = $("<input>");
            $("body").append($temp);
            $temp.val($(element).data('url')).select();
            document.execCommand("copy");
            $temp.remove();
            toastr.success('Скопировано в буфер обмена!');
        }

        /**
         * Сохранение комментария для дочерние url
         * 
         */
         function saveChangeChildrenUrl(obj) {
            data = {
                url: obj.data('url'),
                comment: $('#' + obj.data('url') + '-comment').val(),
            };
            console.log(data)
            $.ajax({
                url: '/save-comment-url',
                method: 'post',
                dataType: "json",
                data: data,
                success: function(data){
                    toastr.success('Комментарий сохранен!');
                },
                error: function (jqXHR, exception) {
                    if (jqXHR.status === 0) {
                        alert('Not connect. Verify Network.');
                    } else if (jqXHR.status == 404) {
                        alert('Requested page not found (404).');
                    } else if (jqXHR.status == 500) {
                        alert('Internal Server Error (500).');
                    } else if (exception === 'parsererror') {
                        alert('Requested JSON parse failed.');
                    } else if (exception === 'timeout') {
                        alert('Time out error.');
                    } else if (exception === 'abort') {
                        alert('Ajax request aborted.');
                    } else {
                        alert('Uncaught Error. ' + jqXHR.responseText);
                    }
                }
            });
        }

        /**
         * Изменение комментария, раблокировка кнопки
         * 
         */
        function changeComment(obj) {
            parent = obj.parent().parent();
            button = parent.find('.save-change-children-url')
            button.attr('disabled', false)
        }

        $(document).on('change', '.change-attribute', function(){ changeAttribute($(this)) })
        $(document).on('change', '.comment', function(){ changeComment($(this)) });
        $(document).on('click', '#button-active', function(){ activeUrlRequest() })
        $(document).on('click', '#button-payment', function(){ generateUrlRequest() })
        $(document).on('click', '.copy', function(){ copyToClipboard($(this)) });
        $(document).on('click', '.save-change-children-url', function(){ saveChangeChildrenUrl($(this)) });
    </script>
@endsection

@section('description', 'AMISAMI Админская url')
@section('keywords', 'AMISAMI, Админская url')
@section('title', 'AMISAMI Админская url')