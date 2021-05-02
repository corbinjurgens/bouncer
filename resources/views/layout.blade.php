<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
		
		
		<!-- bootstrap -->
		<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ho+j7jyWK8fNQe+A12Hb8AhRq26LrZ/JpcUGGOn+Y7RsweNrtN/tE3MoK7ZeZDyx" crossorigin="anonymous"></script>
		
		
		
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" integrity="sha384-B0vP5xmATw1+K9KRQjQERJvTumQW0nPEzvF6L/Z6nronJ3oUOFUFpCjEUQouq2+l" crossorigin="anonymous">
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css">
		
		
		
		@stack('styles')
        <!-- Styles -->
        <style>
         
			@stack('style')
        </style>
		
    </head>
    <body>
		<header class="navbar navbar-expand-lg navbar-light bg-light shadow-lg sticky-top">
		
		<a class="navbar-brand" href="/">{{ env('APP_NAME') }}</a>
		
		 
		<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
			<span class="navbar-toggler-icon"></span>
		</button>
		  <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
			<div class="navbar-nav mr-auto">
					<a class="nav-link" href="#" title="Example Button"><i class="bi bi-box-arrow-in-up-right"></i><span class="d-lg-none"> Example Button</span></a>
			</div>
			<div class="navbar-nav">
				 <li class="nav-item dropdown ">
				  
					<a title="Language" class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						
						<i class="bi bi-flag-fill"></i>
						<span class="d-lg-none"> Language</span>
					</a>
					<div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
						<a class="dropdown-item active" href="" >English</a>
					</div>
				  </li>
			</div>
			<div class="navbar-nav d-lg-none">
				@yield('sidebar')
			</div>
		  </div>
		</header>
		
		
		
		<div class="container-fluid">
			<div class="row">
				<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-lg-block sidebar-dark collapse" style="">
				  <div class="sidebar-sticky pt-3">
					@yield('sidebar')
					
				  </div>
				</nav>
				
				<div class="mr-sm-auto col-lg-8 px-md-4 my-5">
					<!-- Main body when extending from this layout  -->
					@yield('body')
			</div>
		</div>
		
		<!-- Scipts are sent from the permissions example template to this section  -->
		@stack('scripts')
		
		<script>
			@stack('script')
			@stack('javascript')
			$(function(){
				@stack('jquery')
			})
		</script>
		
		
    </body>
</html>
