<div class='row bg-white filter g-1 justify-content-center text-center'
style="
    position: relative;

    top: 35px;
"
>

    <div class='col-12 col-sm-12 col-md-3 col-lg-3 col-xl-3  align-items-stretch'>
        <div class="form-floating">
            <select class="form-select border-left filters-change" id="filtersAdvertising" aria-label="Floating label select example">
              <option selected value=''>Не выбрана</option>
              @foreach ($filtersAdvertising as $item)
                    <option value="{{ $item->id }}">{{ $item->name }}</option>
              @endforeach
            </select>
            <label for="filtersAdvertising">Рекламная сеть</label>
          </div>
    </div>

    <div class='col-12 col-sm-12 col-md-3 col-lg-3 col-xl-3  align-items-stretch'>
        <div class="form-floating">
            <select class="form-select filters-change" id="filtersSpheres" aria-label="Floating label select example">
              <option selected value=''>Не выбрана</option>
              @foreach ($filtersSpheres as $item)
                    <option value="{{ $item->id }}">{{ $item->name }}</option>
              @endforeach
            </select>
            <label for="filtersSpheres">Сфера</label>
          </div>
    </div>

    <div class='col-12 col-sm-12 col-md-3 col-lg-2 col-xl-2  align-items-stretch'>
        <div class="form-floating">
            <input type="number" class="form-control filters-change" id="filtersPriceValueMin" placeholder="name@example.com">
            <label for="filtersPriceValueMin">Цена от</label>
        </div>
    </div>

    <div class='col-12 col-sm-12 col-md-3 col-lg-2 col-xl-2  align-items-stretch'>
        <div class="form-floating">
            <input type="number" class="form-control border-right filters-change" id="filtersPriceValueMax" placeholder="name@example.com">
            <label for="filtersPriceValueMax">Цена до</label>
        </div>
    </div>

    <div class='col-12 col-sm-12 col-md-12 col-lg-2 col-xl-2 d-flex align-items-stretch justify-content-center text-center'>
        <button type="button" class="btn  button-show-custom"><a href="/" style='color:white; text-decoration: none;'> Сбросить фильтр</a></button>
    </div>
</div>