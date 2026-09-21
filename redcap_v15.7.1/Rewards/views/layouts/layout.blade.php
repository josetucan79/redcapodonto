
@php(include $APP_PATH_DOCROOT . 'ProjectGeneral/header.php')

<div class="projhdr"><i class="fas fa-gift"></i> {{$lang['rewards_feature_name']}}@yield('title')</div>

@include('partials.tabs')

<!-- Dynamic Content Start -->
@yield('content')
<!-- Dynamic Content End -->

@php(include $APP_PATH_DOCROOT . 'ProjectGeneral/footer.php')
