@extends('admin.layouts.layout')
@section('header')
    <h1>
        PAGOS
        <small>Listado</small>
    </h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.payment.create') }}"><i class="fa fa-dashboard"></i> Inicio</a></li>
        <li class="active"><a href="{{ route('admin.payment.index') }}"><i class="fa fa-credit-card"></i>Compras</a></li>
        <li class="active"><a href=""><i class="fa fa-pencil"></i>Editar</a></li>
    </ol>
@endsection
@section('content')

    <div class="box">
        <div class="box-header">
            <h3 class="box-title">Editar Compras</h3>
            <a
                    href="{{ route('admin.payment.index') }}"
                            class="btn btn-primary pull-right"
            >
            Volver
            </a>
        </div>
        <div class="box-body">
            {!! Form::model($payment,['action' => ['Admin\PaymentsController@update', $payment->id], 'method' => 'PUT']) !!}

            <div class="form-group {{ $errors->has('user_id') ? 'has-error' : '' }}">
                <label for="">Usuario </label>
                <select name="user_id" class="form-control select2">
                    <option value=" ">Ninguno</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}"
                                {{ old('user_id', $payment->user_id) == $user->id ? 'selected' : '' }}
                        >{{ $user->name }}</option>
                    @endforeach
                </select>
                {!! $errors->first('user_id', '<span class="help-block">:message</span>') !!}
            </div>

            <div class="form-group {{ $errors->has('document_id') ? 'has-error' : '' }}">
                <label for="">Documento</label>
                <select name="document_id" class="form-control select2">
                    <option value=" ">Ninguno</option>
                    @foreach($documents as $document)
                        <option value="{{ $document->id }}"
                                {{ old('document_id', $payment->document_id) == $document->id ? 'selected' : '' }}
                        >{{ $document->name }}</option>
                    @endforeach
                </select>
                {!! $errors->first('document_id', '<span class="help-block">:message</span>') !!}
            </div>



            <div class="form-group {{ $errors->has('amount') ? 'has-error' : '' }}">
                <label for="">Precio</label>
                <input type="text" name="amount" class="form-control"
                       value="{{ old('amount', $payment->amount) }}"
                       placeholder="Escribe el precio">
                {!! $errors->first('amount', '<span class="help-block">:message</span>') !!}
            </div>

            {{ Form::bsSubmit('Editar', ['class'=>'btn btn-primary']) }}
            {!! Form::close() !!}
        </div>
    </div>

@endsection