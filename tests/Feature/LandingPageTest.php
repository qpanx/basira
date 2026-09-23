<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests can explore the English landing page with three proposed plans', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertSee('dir="ltr"', false)
        ->assertSee('BASIRA — Intelligent mobility. Connected care.')
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('locale', 'en')
            ->where('authenticated', false)
            ->where('copy.hero.title', 'Freedom to Explore.')
            ->where('copy.pricing.soon', 'Coming soon')
            ->has('copy.pricing.plans', 3)
            ->has('copy.faq.items', 7));
});

test('switching language persists Arabic copy and RTL markup', function () {
    $this->from(route('home'))->get(route('locale.switch', ['locale' => 'ar']))
        ->assertRedirect(route('home'))
        ->assertSessionHas('locale', 'ar');

    $this->get(route('home'))
        ->assertOk()
        ->assertHeader('Content-Language', 'ar')
        ->assertSee('lang="ar" dir="rtl"', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('locale', 'ar')
            ->where('copy.hero.title', 'حرية الاستكشاف.')
            ->where('copy.pricing.soon', 'قريبًا')
            ->has('copy.pricing.plans', 3));
});

test('an explicit English selection overrides the browser Arabic preference', function () {
    $this->withSession(['locale' => 'en'])->withHeader('Accept-Language', 'ar')
        ->get(route('home'))
        ->assertHeader('Content-Language', 'en')
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

test('existing authenticated users retain dashboard navigation', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('authenticated', true));
});
