<section>
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