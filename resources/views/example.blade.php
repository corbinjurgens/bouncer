@extends('bouncer::layout')

@section('body')
	
	<h1>{{ !is_null($authority) ? $authority->name : 'Everyone' }}</h1>
	
	@if(!$post_url)
		<div class="alert alert-info">
		Your test form doesn't have a $post_url. Set one to test result of the form by passing a url to formExample()
		</div>
	@endif
	
	<form action="{{ $post_url }}" method="POST" enctype="multipart/form-data">
		@csrf
		@method('POST')
		
		<input type="hidden" name="id" value="{{ $authority ? $authority->getKey() : '' }}">
		<input type="hidden" name="mode" value="{{ $mode }}">
		
		@include('bouncer::permissions', ['table_permissions' => $table_permissions, 'permissions' => $permissions])
		
		<input type="submit" class="btn btn-primary" value="Submit">
	</form>
@endsection