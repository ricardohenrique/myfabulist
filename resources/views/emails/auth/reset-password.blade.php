@extends('emails.layout')

@section('title', 'Reset your Purplelist password')
@section('preheader', 'Use this secure link to choose a new Purplelist password.')
@section('eyebrow', 'Account recovery')
@section('heading', 'Let’s get you back to your lists.')

@section('content')
    <p style="margin:0 0 16px;color:#566471;font-size:16px;line-height:1.7;">Hi {{ $user->name }},</p>
    <p style="margin:0;color:#566471;font-size:16px;line-height:1.7;">
        We received a request to reset your Purplelist password. Choose a new password using the secure link below.
    </p>
@endsection

@section('actionUrl', $resetUrl)
@section('actionLabel', 'Reset my password')

@section('afterAction')
    <p style="margin:0;color:#7b8791;font-size:13px;line-height:1.65;">
        This link expires in {{ $expiresInMinutes }} minutes. If you did not request a password reset, no changes are needed and you can safely ignore this email.
    </p>
@endsection
