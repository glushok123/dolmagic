{{-- This file is used to store sidebar items, inside the Backpack admin panel --}}
<!--li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></!--li-->


<li class="nav-item"><a class="nav-link" href="{{ backpack_url('user') }}"><i class="nav-icon la la-users"></i> Users</a></li>
<li class='nav-item'><a class='nav-link' href='{{ url('/admin/statistics') }}'><i class='nav-icon la la-terminal'></i> Статистика</a></li>


<!-- Users, Roles, Permissions -->
<li class="nav-item nav-dropdown">
    <a class="nav-link nav-dropdown-toggle" href="#"><i class="nav-icon la la-store-alt"></i> Площадки</a>
    <ul class="nav-dropdown-items">
        <li class="nav-item"><a class="nav-link" href="{{ backpack_url('shop') }}"><i class="nav-icon la la-store-alt"></i> Список</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ backpack_url('group-shop') }}"><i class="nav-icon la la-layer-group"></i> Группы</a></li>
    </ul>
</li>



<li class='nav-item'><a class='nav-link' href='{{ backpack_url('log') }}'><i class='nav-icon la la-terminal'></i> Logs</a></li>
