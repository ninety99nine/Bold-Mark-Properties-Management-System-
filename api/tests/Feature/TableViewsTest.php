<?php

use App\Models\TableView;
use Illuminate\Support\Str;

// ──────────────────────────────────────────────────────────────────────────────
// index — GET /table-views?context=units
// ──────────────────────────────────────────────────────────────────────────────

it('table views index returns 401 without auth', function () {
    $this->getJson(route('api.v1.index.table-views') . '?context=units')
        ->assertUnauthorized();
});

it('table views index requires context', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.index.table-views'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('context');
});

it('table views index rejects invalid context', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.index.table-views') . '?context=properties')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('context');
});

it('table views index returns views for the requested context', function () {
    $user = adminUser();
    $view = TableView::factory()->create([
        'user_id'         => $user->id,
        'organization_id' => $user->organization_id,
        'context'         => 'units',
        'name'            => 'My Units View',
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.index.table-views') . '?context=units')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($view->id);
});

it('table views index does not return views from a different context', function () {
    $user = adminUser();
    TableView::factory()->create([
        'user_id'         => $user->id,
        'organization_id' => $user->organization_id,
        'context'         => 'invoices',
        'name'            => 'Invoice View',
    ]);

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.index.table-views') . '?context=units')
        ->assertOk()
        ->json('data');

    expect($data)->toBeEmpty();
});

it('table views index does not return another users views', function () {
    $otherUser = adminUser();
    TableView::factory()->create([
        'user_id'         => $otherUser->id,
        'organization_id' => $otherUser->organization_id,
        'context'         => 'units',
    ]);

    $myUser = adminUser();

    $data = $this->actingAs($myUser, 'api')
        ->getJson(route('api.v1.index.table-views') . '?context=units')
        ->assertOk()
        ->json('data');

    expect($data)->toBeEmpty();
});

it('table views index each entry has expected fields', function () {
    $user = adminUser();
    TableView::factory()->create([
        'user_id'         => $user->id,
        'organization_id' => $user->organization_id,
        'context'         => 'units',
        'name'            => 'My View',
    ]);

    $item = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.index.table-views') . '?context=units')
        ->assertOk()
        ->json('data.0');

    expect($item)->toHaveKeys([
        'id', 'user_id', 'organization_id', 'context', 'name',
        'date_range', 'date_range_start', 'date_range_end',
        'filters', 'sort_field', 'sort_direction', 'created_at', 'updated_at',
    ]);
});

it('table views index returns views ordered oldest first', function () {
    $user = adminUser();

    $first = TableView::factory()->create([
        'user_id'         => $user->id,
        'organization_id' => $user->organization_id,
        'context'         => 'invoices',
        'name'            => 'First',
        'created_at'      => now()->subHour(),
    ]);
    $second = TableView::factory()->create([
        'user_id'         => $user->id,
        'organization_id' => $user->organization_id,
        'context'         => 'invoices',
        'name'            => 'Second',
        'created_at'      => now(),
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.index.table-views') . '?context=invoices')
        ->assertOk()
        ->json('data.*.id');

    expect($ids[0])->toBe($first->id);
    expect($ids[1])->toBe($second->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// store — POST /table-views
// ──────────────────────────────────────────────────────────────────────────────

it('table views store returns 401 without auth', function () {
    $this->postJson(route('api.v1.create.table-view'), [])
        ->assertUnauthorized();
});

it('table views store persists and returns the view', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.table-view'), [
            'context' => 'units',
            'name'    => 'Overdue Units',
        ])
        ->assertOk();

    expect(TableView::where('user_id', $user->id)->where('name', 'Overdue Units')->exists())->toBeTrue();
    expect($response->json('data.name'))->toBe('Overdue Units');
    expect($response->json('data.context'))->toBe('units');
});

it('table views store requires name', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.table-view'), ['context' => 'units'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('table views store requires context', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.table-view'), ['name' => 'My View'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('context');
});

it('table views store rejects invalid context', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.table-view'), [
            'context' => 'properties',
            'name'    => 'My View',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('context');
});

it('table views store rejects invalid date_range value', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.table-view'), [
            'context'    => 'units',
            'name'       => 'My View',
            'date_range' => 'yesterday',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date_range');
});

it('table views store requires date_range_start and end when date_range is custom', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.table-view'), [
            'context'    => 'units',
            'name'       => 'Custom Range',
            'date_range' => 'custom',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date_range_start', 'date_range_end']);
});

it('table views store stores filters as an array', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.table-view'), [
            'context' => 'invoices',
            'name'    => 'Filtered View',
            'filters' => ['status' => 'overdue', 'estate_id' => 'abc'],
        ])
        ->assertOk();

    $view = TableView::where('user_id', $user->id)->first();
    expect($view->filters)->toBe(['status' => 'overdue', 'estate_id' => 'abc']);
});

it('table views store stores sort_field and sort_direction', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.table-view'), [
            'context'        => 'cashbook',
            'name'           => 'Sorted View',
            'sort_field'     => 'amount',
            'sort_direction' => 'desc',
        ])
        ->assertOk();

    $view = TableView::where('user_id', $user->id)->first();
    expect($view->sort_field)->toBe('amount');
    expect($view->sort_direction)->toBe('desc');
});

it('table views store scopes the view to the authenticated user', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.table-view'), [
            'context' => 'users',
            'name'    => 'User Scoped View',
        ])
        ->assertOk();

    $view = TableView::where('name', 'User Scoped View')->first();
    expect($view->user_id)->toBe($user->id);
    expect($view->organization_id)->toBe($user->organization_id);
});

it('table views store rejects sort_direction values other than asc or desc', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.table-view'), [
            'context'        => 'units',
            'name'           => 'View',
            'sort_direction' => 'random',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sort_direction');
});

// ──────────────────────────────────────────────────────────────────────────────
// update — PUT /table-views/{tableView}
// ──────────────────────────────────────────────────────────────────────────────

it('table views update returns 401 without auth', function () {
    $user = adminUser();
    $view = TableView::factory()->create([
        'user_id'         => $user->id,
        'organization_id' => $user->organization_id,
        'context'         => 'units',
    ]);

    $this->putJson(route('api.v1.update.table-view', $view), ['name' => 'x'])
        ->assertUnauthorized();
});

it('table views update changes the view name', function () {
    $user = adminUser();
    $view = TableView::factory()->create([
        'user_id'         => $user->id,
        'organization_id' => $user->organization_id,
        'context'         => 'units',
        'name'            => 'Old Name',
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.table-view', $view), ['name' => 'New Name'])
        ->assertOk();

    expect($view->fresh()->name)->toBe('New Name');
});

it('table views update changes the date_range', function () {
    $user = adminUser();
    $view = TableView::factory()->create([
        'user_id'         => $user->id,
        'organization_id' => $user->organization_id,
        'context'         => 'invoices',
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.table-view', $view), ['date_range' => 'this_month'])
        ->assertOk();

    expect($view->fresh()->date_range)->toBe('this_month');
});

it('table views update changes the filters array', function () {
    $user = adminUser();
    $view = TableView::factory()->create([
        'user_id'         => $user->id,
        'organization_id' => $user->organization_id,
        'context'         => 'cashbook',
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.table-view', $view), [
            'filters' => ['type' => 'credit'],
        ])
        ->assertOk();

    expect($view->fresh()->filters)->toBe(['type' => 'credit']);
});

it('table views update returns 403 when another user tries to update the view', function () {
    $owner = adminUser();
    $view  = TableView::factory()->create([
        'user_id'         => $owner->id,
        'organization_id' => $owner->organization_id,
        'context'         => 'units',
    ]);

    $otherUser = adminUser();

    $this->actingAs($otherUser, 'api')
        ->putJson(route('api.v1.update.table-view', $view), ['name' => 'Hijacked'])
        ->assertForbidden();
});

it('table views update returns 404 for a non-existent view', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.table-view', Str::uuid()), ['name' => 'x'])
        ->assertNotFound();
});

it('table views update returns a success message', function () {
    $user = adminUser();
    $view = TableView::factory()->create([
        'user_id'         => $user->id,
        'organization_id' => $user->organization_id,
        'context'         => 'units',
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.table-view', $view), ['name' => 'Updated'])
        ->assertOk()
        ->assertJsonFragment(['message' => 'Updated successfully']);
});

// ──────────────────────────────────────────────────────────────────────────────
// destroy — DELETE /table-views/{tableView}
// ──────────────────────────────────────────────────────────────────────────────

it('table views destroy returns 401 without auth', function () {
    $user = adminUser();
    $view = TableView::factory()->create([
        'user_id'         => $user->id,
        'organization_id' => $user->organization_id,
        'context'         => 'units',
    ]);

    $this->deleteJson(route('api.v1.delete.table-view', $view))
        ->assertUnauthorized();
});

it('table views destroy removes the view from the database', function () {
    $user = adminUser();
    $view = TableView::factory()->create([
        'user_id'         => $user->id,
        'organization_id' => $user->organization_id,
        'context'         => 'units',
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.table-view', $view))
        ->assertOk();

    expect(TableView::find($view->id))->toBeNull();
});

it('table views destroy returns a success message', function () {
    $user = adminUser();
    $view = TableView::factory()->create([
        'user_id'         => $user->id,
        'organization_id' => $user->organization_id,
        'context'         => 'units',
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.table-view', $view))
        ->assertOk()
        ->assertJsonFragment(['message' => 'View deleted']);
});

it('table views destroy returns 403 when another user tries to delete the view', function () {
    $owner = adminUser();
    $view  = TableView::factory()->create([
        'user_id'         => $owner->id,
        'organization_id' => $owner->organization_id,
        'context'         => 'units',
    ]);

    $otherUser = adminUser();

    $this->actingAs($otherUser, 'api')
        ->deleteJson(route('api.v1.delete.table-view', $view))
        ->assertForbidden();
});

it('table views destroy returns 404 for a non-existent view', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.table-view', Str::uuid()))
        ->assertNotFound();
});
