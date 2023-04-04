@extends('layouts.app')

@section('content')



<div class="container" id='app-static'>

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

    <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade row active show" id="sales" role="tabpanel" aria-labelledby="home-tab">
            <div class='row justify-content-center text-center titel-tab'>
                <h3>Продажи</h3>
                <hr>
            </div>

            @include('statistics.sales')

        </div>
        <div class="tab-pane fade row" id="products" role="tabpanel" aria-labelledby="profile-tab">
            <div class='row justify-content-center text-center titel-tab'>
                <h3>Товары</h3>
            </div>

            @include('statistics.products')
            
        </div>
        <div class="tab-pane fade row" id="refunds" role="tabpanel" aria-labelledby="contact-tab">
            <div class='row justify-content-center text-center titel-tab'>
                <h3>Возвраты</h3>
                <hr>
            </div>

            @include('statistics.refunds')

        </div>
    </div>
</div>


@endsection


@push('scripts')
    <script src="https://cdn.amcharts.com/lib/5/index.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/percent.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/radar.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/plugins/json.js"></script>
@endpush

@section('after_scripts')
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
    </style>
@endsection