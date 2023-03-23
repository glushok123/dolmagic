<div class='row'>
    <div class='col-12 col-sm-12 col-md-4 col-lg-4 col-xl-4 custom-border-col'>
        <div class='row'>
            <div class="mb-3">
                <span for="exampleInputEmail1" class="form-label">KOLSYL</span>
                <input type="number" class="form-control change-config" id="KOLSYL" aria-describedby="getKOLSYL" value='{{ $serviceConfigurations->getKOLSYL() }}'>
                <div id="KOLSYLHelp" class="form-text">количество ссылок, генерирующихся после активации аккаунта для передачи своим доверенным</div>
            </div>
        </div>

        <div class='row'>
            <div class="mb-3">
                <label for="exampleInputEmail1" class="form-label">PAYLEVEL</label>
                <input type="number" class="form-control change-config" id="PAYLEVEL" aria-describedby="PAYLEVEL"  value='{{ $serviceConfigurations->getPAYLEVEL() }}'>
                <div id="PAYLEVELHelp" class="form-text">на каком уровне своей ветки каждый получает бонус от взносов</div>
            </div>
        </div>

        <div class='row'>
            <div class="mb-3">
                <label for="exampleInputEmail1" class="form-label">PAYSUM</label>
                <input type="number" class="form-control change-config" id="PAYSUM" aria-describedby="PAYSUM"  value='{{ $serviceConfigurations->getPAYSUM() }}'>
                <div id="PAYSUMHelp" class="form-text">сумма, которую платит каждый участник за себя и за каждого доверенного, если соглашается участвовать в проекте</div>
            </div>
        </div>

        <div class='row'>
            <div class="mb-3">
                <label for="exampleInputEmail1" class="form-label">PAYRESERVE</label>
                <input type="number" class="form-control change-config" id="PAYRESERVE" aria-describedby="PAYRESERVE"  value='{{ $serviceConfigurations->getPAYRESERVE() }}'>
                <div id="PAYRESERVEHelp" class="form-text">сумма, которую каждый участник обязуется перевести в конце первого этапа Проекта</div>
            </div>
        </div>

        <hr>
        <div class='row  justify-content-center'>
            <button type="button" class="btn btn-primary" style='max-width:130px;' id='button-start' disabled>Старт</button>
        </div>
        <hr>
    </div>
    <div class='col-12 col-sm-12 col-md-4 col-lg-4 col-xl-4 '>
        <div class='row'>
            <div class="mb-3">
                <label for="phone" class="form-label">Способы оплаты</label>
                <textarea class="form-control change-attribute" rows="2" placeholder='Способы оплаты' id="payment_method">{{ $model->payment_method }}</textarea>
                <div id="phoneHelp" class="form-text">*обязательно</div>
            </div>
        </div>
    </div>
</div>