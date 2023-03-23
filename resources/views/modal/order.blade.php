<!-- Modal -->
<div class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
      <div class="modal-content">
        <div class="modal-header">
            <div class="container">
                <div class="row text-center">
                    <h4 id='modal-id-bundleid' data-bundleid='0'>Связка #123</h4>
                    <hr>
                    <h3>Оставьте заявку</h3>
                    <span >Мы перезвоним и всё расскажем</span>
                </div>
            </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="container">
                <form>
                    <div class="form-group">
                        <label for="exampleInputEmail1">Полное имя *</label>
                        <input type="text" class="form-control" id="order-name" aria-describedby="emailHelp" >
                    </div>
                    <br>
                    <div class="form-group">
                        <label for="exampleInputPassword1">Номер телефона *</label>
                        <input type="text" class="form-control" id="order-phone" placeholder="+7 (999) 999-99-99">
                    </div>
                </form>
                <br>
                <div class="row">
                    <button type="button" class="btn btn-primary" id="order-send">Отправить</button>
                </div>
            </div>
        </div>

      </div>
    </div>
  </div>