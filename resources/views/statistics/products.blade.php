<section>
    <div class='row'>
        <div class='col'>
            <label for="disabledSelect" class="form-label">Интервал</label>
            <select class="form-select" aria-label="Default select example" id='step-interval-products'>
                <option selected value="day">День</option>
                <!--option value="week">Неделя</option-->
                <option value="month">Месяц</option>
                <option value="year">Год</option>
            </select>
        </div>

        <div class='col'>
            <label for="disabledSelect" class="form-label">Склады</label>
            <div class="">
                <select class="selectpicker" multiple aria-label="size 3 select example"  id='warehouses-products'>
                  <option selected value="all">Все</option>
                        @foreach ($warehouses as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                </select>
            </div>
        </div>

        <div class='col'>
            <label for="disabledSelect" class="form-label">Единица измерения</label>
            <select class="form-select" aria-label="Default select example" id='count-products'>
                <option selected value="count">шт</option>
            </select>
        </div>
    </div>
    <br>
    <hr>
    <div class="row text-center">
        <div class='col my-auto'>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="" id="flexCheckCheckedDiagramma-products" checked>
                <label class="form-check-label" for="flexCheckCheckedDiagramma">
                        Линейная
                </label>
              </div>
        </div>
        <div class='col bg-info date-custom'>
            <label for="disabledSelect" class="form-label fw-bolder">Дата начальная</label>
            <input type="date" class="form-control" id="date-start-products" >
        </div>
        <div class='col bg-info date-custom'>
            <label for="disabledSelect" class="form-label fw-bolder">Дата конечная</label>
            <input type="date" class="form-control" id="date-end-products">
        </div>
        <div class='col my-auto'>
            <button type="button" class="btn btn-outline-primary" id='get-grafics-products'>Сформировать по отдельности</button>
            <hr>
            <button type="button" class="btn btn-outline-primary" id='get-union-products'>Сформировать объединенную</button>
        </div>
    </div>
    <br>
    <hr>
    <div class="row">
        <div id="chart-products" class='chart-sales'></div>
        @php
            $count = 1;
        @endphp
        @while ($count != 200)
            <div id="chart-products-{{$count}}" class='chart-sales'></div>
            @php
                $count = $count + 1;
            @endphp
        @endwhile

    </div>
</section>