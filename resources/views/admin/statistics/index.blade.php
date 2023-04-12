@extends(backpack_view('blank'))

@section('content')

<div class="bg-light" id='app-static'>

    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="home-tab" data-bs-toggle="tab" data-bs-target="#sales" type="button" role="tab" aria-controls="home" aria-selected="true" data-type='sales'>Продажи</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="profile-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab" aria-controls="profile" aria-selected="false" data-type='products'>Товары</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#refunds" type="button" role="tab" aria-controls="contact" aria-selected="false" data-type='refunds'>Возвраты</button>
        </li>
    </ul>

    <div class="tab-content bg-light" id="myTabContent">
        <div class="tab-pane fade row active show" id="sales" role="tabpanel" aria-labelledby="home-tab">
            <div class='row justify-content-center text-center titel-tab'>
                <h3>Продажи</h3>
                <hr>
            </div>

            @include('admin.statistics.sales')

        </div>
        <div class="tab-pane fade row" id="products" role="tabpanel" aria-labelledby="profile-tab">
            <div class='row justify-content-center text-center titel-tab'>
                <h3>Товары</h3>
            </div>

            @include('admin.statistics.products')
            
        </div>
        <div class="tab-pane fade row" id="refunds" role="tabpanel" aria-labelledby="contact-tab">
            <div class='row justify-content-center text-center titel-tab'>
                <h3>Возвраты</h3>
                <hr>
            </div>

            @include('admin.statistics.refunds')

        </div>
    </div>
</div>


<link rel="dns-prefetch" href="//fonts.gstatic.com">
<link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.3/css/jquery.dataTables.min.css" rel="stylesheet">
<link href="{{ asset('js/bootstrap/bootstrap.css') }}" rel="stylesheet">

<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css"
integrity="sha384-gfdkjb5BdAXd+lj+gudLWI+BXq4IuLW5IT+brZEZsLFm++aCMlF1V92rMkPaX4PP" crossorigin="anonymous">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.14.0-beta2/css/bootstrap-select.min.css" integrity="sha512-mR/b5Y7FRsKqrYZou7uysnOdCIJib/7r5QeJMFvLNHNhtye3xJp1TdJVPLtetkukFn227nKpXD9OjUc09lx97Q==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.14.0-beta2/js/bootstrap-select.min.js" integrity="sha512-FHZVRMUW9FsXobt+ONiix6Z0tIkxvQfxtCSirkKc5Sb4TKHmqq1dZa8DphF0XqKb3ldLu/wgMa8mT6uXiLlRlw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<!-- Bootstrap Font Icon CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css">
<link href="{{ asset('css/app.css') }}" rel="stylesheet">

<script src="{{ asset('js/toastr/toastr.js') }}" ></script>
<script src="https://kit.fontawesome.com/6a4e5ddf0a.js" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>
<script src="https://cdn.datatables.net/1.13.3/js/jquery.dataTables.min.js"></script>
<script src="https://yastatic.net/es5-shims/0.0.2/es5-shims.min.js"></script>
<script src="https://yastatic.net/share2/share.js"></script>
<link href="{{ asset('js/toastr/toastr.css') }}" rel="stylesheet">
<script src="{{ asset('js/app.js') }}" defer></script>

<script src="https://cdn.amcharts.com/lib/5/index.js"></script>
<script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
<script src="https://cdn.amcharts.com/lib/5/percent.js"></script>
<script src="https://cdn.amcharts.com/lib/5/radar.js"></script>
<script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
<script src="https://cdn.amcharts.com/lib/5/plugins/json.js"></script>

<script src="{{ asset('js/statistics/statistics.js') }}"></script>

<style>
    .titel-tab{
        margin:15px;
    }
    .chart-sales
    {
        width: 100%;
        height: 500px;
    }
    .body, .clearfix{
        display: none;
    }
    .date-custom{
        padding:10px;
        margin :10px;
        border-radius: 10px;
    }
    .hidden{
        display:none;
    }
    /********************  Preloader Demo-11 *******************/
        .loader11{width:100px;height:70px;margin:50px auto;position:relative}
        .loader11 span{display:block;width:5px;height:10px;background:#e43632;position:absolute;bottom:0;animation:loading-11 2.25s infinite ease-in-out}
        .loader11 span:nth-child(2){left:11px;animation-delay:.2s}
        .loader11 span:nth-child(3){left:22px;animation-delay:.4s}
        .loader11 span:nth-child(4){left:33px;animation-delay:.6s}
        .loader11 span:nth-child(5){left:44px;animation-delay:.8s}
        .loader11 span:nth-child(6){left:55px;animation-delay:1s}
        .loader11 span:nth-child(7){left:66px;animation-delay:1.2s}
        .loader11 span:nth-child(8){left:77px;animation-delay:1.4s}
        .loader11 span:nth-child(9){left:88px;animation-delay:1.6s}
        @-webkit-keyframes loading-11{
            0%{height:10px;transform:translateY(0);background:#ff4d80}
            25%{height:60px;transform:translateY(15px);background:#3423a6}
            50%{height:10px;transform:translateY(-10px);background:#e29013}
            100%{height:10px;transform:translateY(0);background:#e50926}
        }
        @keyframes loading-11{
            0%{height:10px;transform:translateY(0);background:#ff4d80}
            25%{height:60px;transform:translateY(15px);background:#3423a6}
            50%{height:10px;transform:translateY(-10px);background:#e29013}
            100%{height:10px;transform:translateY(0);background:#e50926}
        }
    .form-check-label{
        margin-left: 25px !important;
    }
</style>
@endsection