<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders every static workspace review state', function (string $state) {
    $this->get(route('prototype.show', ['view' => $state]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('prototype/workspace')
            ->where('initialView', $state));
})->with(['inbox', 'list', 'starred', 'empty', 'complete']);

it('renders the static inertia login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/login'));
});

it('renders the static inertia registration page', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/register'));
});
