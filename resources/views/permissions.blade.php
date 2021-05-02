@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/handlebars.js/4.0.5/handlebars.min.js" integrity="sha512-PjbpC9E7cx3jU8vrz0Bqo8DNofDrLOoV94DXxx5PK8T4udhDrUcjnEiqLA/SD6YivgHE0pwGlC8RSnwxXXXI4w==" crossorigin="anonymous"></script>
<x-handlebars.script id="template-add-ability">

	<div class="col-md-3 mb-3 scope-item scope-element">
		<div class="card card-body shadow">
			<h4># @{{ key }} <a class="btn btn-sm close" onclick="deleteCurrent(this)">&times;</a></h4>
			
			@{{#each abilities}}
				<input name="@{{@root._prefix}}[@{{@root.key}}][@{{this}}][checked]" type="hidden" value="0">
				<div class="form-check-inline form-check">
					<input checked class="form-check-input input-checked" name="@{{@root._prefix}}[@{{@root.key}}][@{{this}}][checked]" type="checkbox" value="1">
					<label class="form-check-label">@{{#if (lookup @root.ability_names this)}}@{{lookup @root.ability_names this}}@{{else}}@{{this}}@{{/if}}</label>
				</div>
			@{{/each}}
			
		</div>
	</div>
</x-handlebars.script>

@endpush

@push('script')
	var ability_names;
	var platform_template;
	function deleteCurrent(e){
		scope = $(e).closest('.scope-element');
		$(scope).remove();
	}
	var add_id, add_table, add_prefix, add_abilities, add_key;
	function openModal(){
		$('#add-modal').modal('show');
		$('#modal-table').html(add_table);
	}
	function addAbility(e){
		parent = $(e).closest('.permission-level');
		add_table = $(parent).attr('data-table');
		add_id = $(parent).attr('id');
		add_prefix = $(parent).attr('data-prefix');
		add_abilities = JSON.parse( $(parent).attr('data-abilities') );
		openModal();
	}
	function executeAddAbility(){
		key = $('#modal-key').val();
		if (isNaN(key) == true){
			alert('You must select numeric #ID');
			return;
		}
		add_key = key;
		data = {
			'_prefix':add_prefix,
			'abilities':add_abilities,
			'key':add_key,
			'ability_names':ability_names
			
		}
		template = platform_template(data);
		console.log(template);
		$('#add-modal').modal('hide');
		$('#'+add_id).find('.scope-container').append(template);
		
	}
	function init_permission_disable(){
		inputs = $('.permission-group');
		$(inputs).each(function(i,l){
			permission_disable(l);
		});
	}
	function permission_disable(e){
		active = ($(e).prop('checked'));
		targets = $(e).closest('.form-group').next();
		if (!$(targets).is('.permission-options')){
			return;
		}
		$(targets).removeClass('bg-light bg-white');
		if (active){
			$(targets).find('input, select, textarea').prop('disabled', false);
			$(targets).addClass('bg-white');
		}else{
			$(targets).find('input, select, textarea').prop('disabled', true);
			$(targets).addClass('bg-light');
		}
	}
	function permission_options(e){
		checked = $(e).prop('checked');
		scope = $(e).closest('.permission-level');
		inputs = $(scope).find('.permission-options input, .permission-options select, .permission-options textarea');
		$(inputs).prop('disabled', !checked);
	}
@endpush

@push('jquery')
	init_permission_disable();
	platform_template = Handlebars.compile($('#template-add-ability').html());
	ability_names = {!! json_encode(['create' => 'create', 'edit' => 'edit', 'delete' => 'delete']) !!};{{-- You could pass an array of your own translations here --}}

@endpush

@push('style')
	.rights-check + img {
		opacity:0.5;
		
	}
	.rights-check:checked + img {
		opacity:1;
		
	}
@endpush

	@if(($roles ?? false))
	
	<p class="h6 mt-5">Roles</p>
	
	<div class="form-group">
		<fieldset role="radiogroup" aria-labelledby="roles-group">
		<legend class="col-form-label text-muted" id="roles-group">Check which roles you want this user to have</legend>
			@foreach($roles as $role_id => $role)	
			<div class="form-check form-check-inline">
				<label class="form-check-label" >
					<input {{ $role['checked'] ? 'checked' : '' }} {{ $role['disabled'] ? 'disabled' : '' }} type="checkbox" name="roles[]" value="{{ $role_id }}" class="form-check-input input-roles">
					{{ $role['name'] }}
				</label>
			</div>
			@endforeach	
		</fieldset>
	</div>
	
	
	
	
	@endif
	
	
	@if(($table_permissions ?? false))
			
	<p class="h6 mt-5">Table Permissions</p>
	<p class="text-muted">Permissions specific to a certain item or table</p>
	
	
	<div class="modal fade" id="add-modal" tabindex="-1" role="dialog" aria-hidden="true">
	  <div class="modal-dialog" role="document">
		<div class="modal-content">
		  <div class="modal-header">
			<h5 class="modal-title" ><span id="modal-table"></span> Add</h5>
			<button type="button" class="close" data-dismiss="modal" aria-label="Close">
			  <span aria-hidden="true">&times;</span>
			</button>
		  </div>
		  <div class="modal-body">
			<input class="form-control" id="modal-key" type="number">
		  </div>
		  <div class="modal-footer">
			<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
			<button type="button" class="btn btn-primary" onClick="executeAddAbility()">Add</button>
		  </div>
		</div>
	  </div>
	</div>

		
		<ul class="list-group shadow mb-3">
		@foreach($table_permissions as $table => $types)
			<li class="position-relative list-group-item lh-condensed bg-white">
				<h4 class="display-4">{{ $table == '*' ? 'All Tables' : $table }} @if( $table == '*' ) <span class=""><i class="bi bi-exclamation-triangle-fill"></i></span> @endif</h4>
				
					@foreach($types as $type => $data)
						@php( $forbid = \Str::startsWith($type, 'forbid') )
						@if(in_array($type, ['general_permissions', 'forbid_general_permissions']))
						<div id="{{ $table }}-{{ $type }}" class="permission-level">
							<p>
								<span class="badge badge-{{ $forbid ? 'danger' : 'success'}}">{{ $forbid ? 'Forbid' : 'Allow' }}</span>
							</p>
							<div class="mb-5">
							@foreach($data['permissions'] as $ability)
									<input type="hidden" value="0" name="table_permissions[{{$table}}][{{$type}}][{{$ability['name']}}][checked]" {{ $ability['disabled'] ? 'disabled' : '' }}>
									
									<div class="form-check-inline form-check ">
										<label class="form-check-label">
										<input type="checkbox" name="table_permissions[{{$table}}][{{$type}}][{{$ability['name']}}][checked]" value="1" class="form-check-input input-checked" {{ $ability['disabled'] ? 'disabled' : '' }} {{ ($ability['checked']) ? 'checked' : '' }}>
											{{ $ability['name'] }}
										</label>
									</div>
									
								
							@endforeach
							</div>
						</div>
						@elseif(in_array($type, ['specific_permissions', 'forbid_specific_permissions']))
						<div id="{{ $table }}-{{ $type }}" class="permission-level" data-table="{{ $table }}" data-prefix="{{ "table_permissions[$table][$type]" }}" data-abilities="{{ json_encode($data['list']) }}">
							<p>
								<span class="badge badge-{{ $forbid ? 'danger' : 'success'}}">{{ $forbid ? 'Forbid' : 'Allow' }} Specific</span>
							</p>
							<div class="mb-5">
								<div class="row scope-container scope-max">
								@foreach($data['permissions'] as $id => $abilities)
									<div class="col-md-3 mb-3 scope-element scope-item">
										<div class="card card-body shadow">
											<h4># {{ $id }} <a class="btn btn-sm close" onclick="deleteCurrent(this)">&times;</a></h4>
										@foreach($abilities as $ability)
											
											<input type="hidden" value="0" name="table_permissions[{{$table}}][{{$type}}][{{$id}}][{{$ability['name']}}][checked]" {{ $ability['disabled'] ? 'disabled' : '' }}>
									
											<div class="form-check-inline form-check ">
												<label class="form-check-label">
												<input type="checkbox" name="table_permissions[{{$table}}][{{$type}}][{{$id}}][{{$ability['name']}}][checked]" value="1" class="form-check-input input-checked" {{ $ability['disabled'] ? 'disabled' : '' }} {{ ($ability['checked']) ? 'checked' : '' }}>
													{{ $ability['name'] }}
												</label>
											</div>
											
										@endforeach
										</div>
									</div>
								@endforeach
								</div>
								<a class="btn btn-{{ $forbid ? 'danger' : 'success'}}" onClick="addAbility(this)">Add</a>
							</div>
						</div>
						@elseif($type == 'anything_permissions')
							@php($ability = $data['permissions'])
							<div id="{{ $table }}-{{ $type }}" class="permission-level">
								<p>
									<span class="badge badge-dark">Anything</span>
								</p>
								<div class="mb-5">
									
									<input type="hidden" value="0" name="table_permissions[{{$table}}][{{$type}}][checked]" {{ $ability['disabled'] ? 'disabled' : '' }}>
							
									<div class="form-check-inline form-check ">
										<label class="form-check-label">
										<input type="checkbox" name="table_permissions[{{$table}}][{{$type}}][checked]" value="1" class="form-check-input input-checked" {{ $ability['disabled'] ? 'disabled' : '' }} {{ ($ability['checked']) ? 'checked' : '' }}>
											{{ $ability['name'] }}
										</label>
									</div>
									
								
								</div>
							</div>
						@elseif($type == 'claim_permissions')
							@php($ability = $data['permissions'])
							<div id="{{ $table }}-{{ $type }}" class="permission-level">
								<p>
									<span class="badge badge-warning">Claim</span>
								</p>
								<div class="mb-5">
									
									<input type="hidden" value="0" name="table_permissions[{{$table}}][{{$type}}][checked]" {{ $ability['disabled'] ? 'disabled' : '' }}>
								
									<div class="form-group">
										<div class="form-check-inline form-check ">
											<label class="form-check-label">
											<input onChange="permission_options(this);permission_disable(this);" type="checkbox" name="table_permissions[{{$table}}][{{$type}}][checked]" value="1" class="form-check-input input-checked" {{ $ability['disabled'] ? 'disabled' : '' }} {{ ($ability['checked']) ? 'checked' : '' }}>
												{{ $ability['name'] }}
											</label>
										</div>
									</div>
									
									<div class="permission-options card card-body mt-2 bg-{{ $ability['checked'] ? 'white' : 'light' }}">
										
										@php($pivot = $ability['pivot_options'] ?? [])
										<div class="row">
											<div class="col-md-6">
												<div class="form-group">
													<label class="w-100"> Max Claims
														<input value="{{ $pivot['max'] ?? '' }}" class="form-control" type="number" {{ ($ability['disabled'] || !$ability['checked']) ? 'disabled' : '' }} name="table_permissions[{{$table}}][{{$type}}][pivot_options][max]" />
													</label>
												</div>
											</div>
											<div class="col-md-6">
											
												<input {{ ($ability['disabled'] || !$ability['checked']) ? 'disabled' : '' }} type="hidden" value="" name="table_permissions[{{$table}}][{{$type}}][pivot_options][abilities]">
												
												@php($claim_abilities = $pivot['abilities'] ?? [])
												<div class="form-group">
													<fieldset role="radiogroup">
														<legend class="col-form-label">Abilities Received Upon Claim. If no abilities are selected, it will default to all abilities</legend>
														@foreach($data['list'] as $claim_ability)
														<div class="form-check form-check-inline">
															<label class="form-check-label">
															<input {{ in_array($claim_ability, $claim_abilities) ? 'checked' : '' }} {{ ($ability['disabled'] || !$ability['checked']) ? 'disabled' : '' }} type="checkbox" name="table_permissions[{{$table}}][{{$type}}][pivot_options][abilities][]" value="{{ $claim_ability }}" class="form-check-input input-checked" {{ $ability['disabled'] ? 'disabled' : '' }} {{ ($ability['checked']) ? 'checked' : '' }}>
																{{ $claim_ability }}
															</label>
														
														</div>
														@endforeach
													</fieldset>
															
												</div>
												
												
												
											</div>
										</div>
									</div>
								</div>
							</div>
						@endif
					@endforeach
			</li>
		@endforeach
		</ul>
	@endif
	
	
	@if(($permissions ?? false))
	
	<p class="h6 mt-5">Simple Permissons</p>
	<p class="text-muted">Abilities not related to a specific table or table entrys</p>
	
	
	<ul class="list-group shadow mb-3">
	@foreach($permissions as $type => $abilities)
		<li class="position-relative list-group-item lh-condensed bg-white">
			<h4 class="display-4">{{ $type }}</h4>
			
			@foreach($abilities as $ability => $data)
					<div id="{{ $type . '-' . $data['name'] }}" class="permission-level">
						<div class="mb-5">
							<div class="form-group">
								<input type="hidden" value="0" name="permissions[{{$type}}][{{$data['name']}}][checked]" {{ $data['disabled'] ? 'disabled' : '' }}>
						
								<div class="form-check-inline form-check ">
									<label class="form-check-label">
									<input type="checkbox" name="permissions[{{$type}}][{{$data['name']}}][checked]" value="1" class="form-check-input input-checked" {{ $data['disabled'] ? 'disabled' : '' }} {{ ($data['checked']) ? 'checked' : '' }}>
										{{ $data['name'] }}
									</label>
								</div>
							</div>
						</div>
					</div>
			@endforeach
		</li>
	@endforeach
	</ul>
	@endif
	
	
		
	<br><br>
