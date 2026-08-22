@extends('emails.layout')

@section('title', 'Confirm your Purplelist email')
@section('preheader', 'Confirm this email address for your Purplelist account.')
@section('eyebrow', 'Email confirmation')
@section('heading', 'One quick check, then you’re set.')

@section('content')
    <p style="margin:0 0 16px;color:#566471;font-size:16px;line-height:1.7;">Hi {{ $user->name }},</p>
    <p style="margin:0;color:#566471;font-size:16px;line-height:1.7;">
        Confirm that this is the email address you want to use with Purplelist. Confirmation remains optional and does not prevent you from signing in.
    </p>
@endsection

@section('actionUrl', $verificationUrl)
@section('actionLabel', 'Confirm email address')
@section('actionTarget', '_blank')

@section('afterAction')
    <p style="margin:0;color:#7b8791;font-size:13px;line-height:1.65;">
        If you did not request this confirmation, you can safely ignore this email.
    </p>
@endsection
