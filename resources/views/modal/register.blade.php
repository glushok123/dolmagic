<!-- Modal -->
<div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
      <div class="modal-content">
        <div class="modal-header">
            <div class="container">
                <div class="row text-center">
                    <h3>Регистрация</h3>
                    <span>Зарегистрируйтесь для входа в кабинет</span>
                </div>
            </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="container">
                <form>
                    <div class="form-group">
                        <label for="exampleInputEmail1">Полное имя *</label>
                        <input type="text" class="form-control" id="register-name" aria-describedby="emailHelp" >
                    </div>
                    <br>
                    <div class="form-group">
                        <label for="exampleInputPassword1">Номер телефона *</label>
                        <input type="text" class="form-control" id="register-phone" placeholder="+7 (999) 999-99-99">
                    </div>
                    <br>
                    <div class="form-group">
                        <label for="exampleInputPassword1">Пароль *</label>
                        <input type="text" class="form-control" id="register-pass" placeholder="***********">
                    </div>
                </form>
                <br>
                <div class="row">
                    <button type="button" class="btn btn-primary" id="register-send">Зарегистрироваться</button>
                </div>
                <br>
                <div class="row text-center">
                    <a href="#">Уже есть аккаунт? Войти</a>
                </div>
            </div>
        </div>

      </div>
    </div>
  </div>