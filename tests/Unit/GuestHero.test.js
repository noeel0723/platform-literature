import assert from 'node:assert/strict';
import test from 'node:test';

import { guestHeroBackdrop, guestHeroFocalStyle } from '../../resources/js/Support/guestHero.js';

test('uses a real backdrop without substituting a portrait cover', () => {
    assert.equal(guestHeroBackdrop({ backdrop_url: ' https://images.example.test/banner.jpg ', cover_url: '/portrait.jpg' }), 'https://images.example.test/banner.jpg');
    assert.equal(guestHeroBackdrop({ backdrop_url: null, cover_url: '/portrait.jpg' }), null);
    assert.equal(guestHeroBackdrop(null), null);
    assert.equal(guestHeroBackdrop({ backdrop_url: '  ' }), null);
});

test('shows the visual fallback only for the backdrop that failed to load', () => {
    assert.equal(guestHeroBackdrop({ backdrop_url: '/first.jpg' }, '/first.jpg'), null);
    assert.equal(guestHeroBackdrop({ backdrop_url: '/second.jpg' }, '/first.jpg'), '/second.jpg');
});

test('provides separate focal defaults for mobile tablet and desktop', () => {
    assert.deepEqual(guestHeroFocalStyle(), {
        '--hero-focal-mobile': '70% 40%',
        '--hero-focal-tablet': '60% 40%',
        '--hero-focal-desktop': '50% 40%',
    });
});

test('applies an individual focal axis without changing the other breakpoint defaults', () => {
    assert.deepEqual(guestHeroFocalStyle({ mobile: { x: 78 }, desktop: { y: 25 } }), {
        '--hero-focal-mobile': '78% 40%',
        '--hero-focal-tablet': '60% 40%',
        '--hero-focal-desktop': '50% 25%',
    });
});

test('clamps focal coordinates to image bounds', () => {
    assert.equal(guestHeroFocalStyle({ mobile: { x: -10, y: 130 } })['--hero-focal-mobile'], '0% 100%');
});

test('ignores nonnumeric or invalid focal settings instead of producing invalid CSS', () => {
    assert.equal(guestHeroFocalStyle({ mobile: { x: 'right', y: null } })['--hero-focal-mobile'], '70% 40%');
    assert.equal(guestHeroFocalStyle({ tablet: { x: Infinity, y: NaN } })['--hero-focal-tablet'], '60% 40%');
    assert.deepEqual(guestHeroFocalStyle(null), guestHeroFocalStyle());
});
