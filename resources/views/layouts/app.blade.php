
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="@yield('description')">
	<meta name="keywords" content="@yield('keywords')">
	<meta name="title" content="@yield('title')" />
	<meta name="robots" content="index,follow" />
	<meta name="revisit-after" content="2 days">
	<meta name="coverage" content="Worldwide">
	<meta name="distribution" content="Global">
	<link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
	<link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
	<!-- CSRF Token -->
	<meta name="csrf-token" content="{{ csrf_token() }}">

	<title>@yield('title')</title>

	<link rel="dns-prefetch" href="//fonts.gstatic.com">
	<link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">
	<link href="https://cdn.datatables.net/1.13.3/css/jquery.dataTables.min.css" rel="stylesheet">
	<link href="{{ asset('js/bootstrap/bootstrap.css') }}" rel="stylesheet">
	<link href="{{ asset('js/toastr/toastr.css') }}" rel="stylesheet">
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css"
	integrity="sha384-gfdkjb5BdAXd+lj+gudLWI+BXq4IuLW5IT+brZEZsLFm++aCMlF1V92rMkPaX4PP" crossorigin="anonymous">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.14.0-beta2/css/bootstrap-select.min.css" integrity="sha512-mR/b5Y7FRsKqrYZou7uysnOdCIJib/7r5QeJMFvLNHNhtye3xJp1TdJVPLtetkukFn227nKpXD9OjUc09lx97Q==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.14.0-beta2/js/bootstrap-select.min.js" integrity="sha512-FHZVRMUW9FsXobt+ONiix6Z0tIkxvQfxtCSirkKc5Sb4TKHmqq1dZa8DphF0XqKb3ldLu/wgMa8mT6uXiLlRlw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

	<link href="{{ asset('css/app.css') }}" rel="stylesheet">

	 

	<script src="{{ asset('js/toastr/toastr.js') }}" ></script>
	<script src="https://kit.fontawesome.com/6a4e5ddf0a.js" crossorigin="anonymous"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
	<script src="https://unpkg.com/axios/dist/axios.min.js"></script>
	<script src="https://cdn.datatables.net/1.13.3/js/jquery.dataTables.min.js"></script>
	<script src="https://yastatic.net/es5-shims/0.0.2/es5-shims.min.js"></script>
	<script src="https://yastatic.net/share2/share.js"></script>

	<script src="{{ asset('js/app.js') }}" defer></script>
	<script>
		$.ajaxSetup({
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			}
		});
	</script>
	@stack('scripts')
</head>

<body class='d-flex flex-column min-vh-100 bg-light'>
	<div id="app">
		<nav class="navbar navbar-expand-lg navbar-light bg-light">
			<div class="container">
			  	<a class="navbar-brand" href="#">
					<img class="_1n3zp94" src="https://s91588.cdn.ngenix.net/shared/static/images/design-system/sravni-logo-sign.png" alt="logo" width="35" height="35">
				</a>
			  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
				<span class="navbar-toggler-icon"></span>
			  </button>
			  <div class="collapse navbar-collapse" id="navbarSupportedContent">
				<ul class="navbar-nav me-auto mb-2 mb-lg-0">
				  <li class="nav-item">
					<a class="nav-link active" aria-current="page" href="/statistics">Статистика </a>
				  </li>
				</ul>
				<!--form class="d-flex">

				  <a class=" nav-item btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#registerModal" href="#">Войти</a>
				</form-->
			  </div>
			</div>
		  </nav>
		<!--nav class="navbar navbar-expand-lg navbar-light bg-light">
			<div class="container-fluid">
			  <a class="navbar-brand" href="{{ url('/') }}">AMISAMI</a>
			  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
				<span class="navbar-toggler-icon"></span>
			  </button>
			  <div class="collapse navbar-collapse" id="navbarSupportedContent">
				<ul class="navbar-nav me-auto mb-2 mb-lg-0">
				  <li class="nav-item"><a class="nav-link" href="{{ url('/searchProduct') }}">Поиск товаров</a></li>

				  @if (backpack_auth()->guest())
						<li class="nav-item"><a class="nav-link" href="{{ backpack_url('login') }}">Авторизация</a> </li> 
					@endif

					@if (backpack_auth()->check())
						<li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false"> {{ backpack_user()->name }}</a>
							<ul class="dropdown-menu" aria-labelledby="navbarDropdown">
								<li><a class="dropdown-item" href="#">Личный кабинет</a></li>
								<li><a class="dropdown-item" href="#">Заказы</a></li>
								<li><a class="dropdown-item" href="#">Настройки</a></li>
								<li><hr class="dropdown-divider"></li>
								<li><a class="dropdown-item" href="{{ url('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" >Выйти из аккаунта</a><form id="logout-form" action="{{ backpack_url('logout') }}" method="POST" class="d-none"> @csrf </form></li>
							</ul>
						</li>
					@endif

				</ul>
			  </div>
			</div>
		  </nav-->
	</div>



	<br>

	@yield('content')



	@yield('before_scripts')

	@yield('after_scripts')

	<style>
		.header-baner-custom{
			margin-top:50px;
		}
		.for-header-baner-custom{

		}
		.nav-item{
			margin-left:8%;
			margin-top:10px;
		}
	</style>
</body>

</html>