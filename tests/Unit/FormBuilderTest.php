<?php

declare(strict_types=1);

namespace Kkxdev\HtmlCompat\Tests\Unit;

use Kkxdev\HtmlCompat\FormBuilder;
use Kkxdev\HtmlCompat\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;

class FormBuilderTest extends TestCase
{
    private FormBuilder $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->form = $this->app->make(FormBuilder::class);
    }

    // =========================================================================
    // Form::open()
    // =========================================================================

    public function test_open_renders_post_form_by_default(): void
    {
        $html = (string) $this->form->open(['url' => '/users']);

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('action="http://localhost/users"', $html);
    }

    public function test_open_renders_get_form(): void
    {
        $html = (string) $this->form->open(['method' => 'GET', 'url' => '/search']);

        $this->assertStringContainsString('method="get"', $html);
        // GET forms must NOT include the CSRF token
        $this->assertStringNotContainsString('_token', $html);
    }

    public function test_open_includes_csrf_token_for_post(): void
    {
        $html = (string) $this->form->open(['url' => '/users']);

        $this->assertStringContainsString('name="_token"', $html);
    }

    public function test_open_adds_extra_html_attributes(): void
    {
        $html = (string) $this->form->open([
            'url'   => '/users',
            'class' => 'needs-validation',
            'id'    => 'user-form',
        ]);

        $this->assertStringContainsString('class="needs-validation"', $html);
        $this->assertStringContainsString('id="user-form"', $html);
    }

    public function test_open_sets_multipart_enctype_for_file_uploads(): void
    {
        $html = (string) $this->form->open(['url' => '/upload', 'files' => true]);

        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
    }

    // =========================================================================
    // Method spoofing
    // =========================================================================

    public function test_put_method_produces_post_form_with_hidden_method(): void
    {
        $html = (string) $this->form->open(['url' => '/users/1', 'method' => 'PUT']);

        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('name="_method"', $html);
        $this->assertStringContainsString('value="PUT"', $html);
    }

    public function test_patch_method_is_spoofed(): void
    {
        $html = (string) $this->form->open(['url' => '/users/1', 'method' => 'PATCH']);

        $this->assertStringContainsString('value="PATCH"', $html);
    }

    public function test_delete_method_is_spoofed(): void
    {
        $html = (string) $this->form->open(['url' => '/users/1', 'method' => 'DELETE']);

        $this->assertStringContainsString('value="DELETE"', $html);
    }

    // =========================================================================
    // Form::close()
    // =========================================================================

    public function test_close_renders_closing_form_tag(): void
    {
        $this->form->open(['url' => '/']);
        $html = (string) $this->form->close();

        $this->assertSame('</form>', $html);
    }

    // =========================================================================
    // Text input
    // =========================================================================

    public function test_text_renders_input(): void
    {
        $html = (string) $this->form->text('name');

        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('name="name"', $html);
    }

    public function test_text_with_explicit_value(): void
    {
        $html = (string) $this->form->text('name', 'Alice');

        $this->assertStringContainsString('value="Alice"', $html);
    }

    public function test_text_applies_class_attribute(): void
    {
        $html = (string) $this->form->text('name', null, ['class' => 'form-control']);

        $this->assertStringContainsString('class="form-control"', $html);
    }

    public function test_text_skips_false_attributes(): void
    {
        $html = (string) $this->form->text('name', null, ['disabled' => false]);

        $this->assertStringNotContainsString('disabled', $html);
    }

    public function test_text_renders_required_attribute(): void
    {
        $html = (string) $this->form->text('name', null, ['required' => true]);

        $this->assertStringContainsString('required', $html);
    }

    public function test_text_renders_data_attributes(): void
    {
        $html = (string) $this->form->text('name', null, ['data-controller' => 'autocomplete']);

        $this->assertStringContainsString('data-controller="autocomplete"', $html);
    }

    public function test_text_renders_aria_attributes(): void
    {
        $html = (string) $this->form->text('name', null, ['aria-label' => 'Full name']);

        $this->assertStringContainsString('aria-label="Full name"', $html);
    }

    // =========================================================================
    // Email / password / hidden / number / textarea
    // =========================================================================

    public function test_email_renders_email_input(): void
    {
        $html = (string) $this->form->email('email', 'a@b.com');

        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('value="a@b.com"', $html);
    }

    public function test_password_renders_without_value(): void
    {
        $html = (string) $this->form->password('pass');

        $this->assertStringContainsString('type="password"', $html);
        $this->assertStringNotContainsString('value=', $html);
    }

    public function test_hidden_renders_hidden_input(): void
    {
        $html = (string) $this->form->hidden('user_id', '42');

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('value="42"', $html);
    }

    public function test_number_renders_number_input(): void
    {
        $html = (string) $this->form->number('age', 25, ['min' => 0, 'max' => 120]);

        $this->assertStringContainsString('type="number"', $html);
        $this->assertStringContainsString('value="25"', $html);
        $this->assertStringContainsString('min="0"', $html);
        $this->assertStringContainsString('max="120"', $html);
    }

    public function test_textarea_renders(): void
    {
        $html = (string) $this->form->textarea('bio', 'Hello world');

        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('Hello world', $html);
    }

    // =========================================================================
    // Select
    // =========================================================================

    public function test_select_renders_with_options(): void
    {
        $html = (string) $this->form->select('status', ['active' => 'Active', 'inactive' => 'Inactive']);

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('value="active"', $html);
        $this->assertStringContainsString('Active', $html);
    }

    public function test_select_marks_selected_option(): void
    {
        $html = (string) $this->form->select('status', ['active' => 'Active', 'inactive' => 'Inactive'], 'inactive');

        $this->assertStringContainsString('selected', $html);
    }

    public function test_select_prepends_placeholder_option(): void
    {
        $html = (string) $this->form->select(
            'status',
            ['active' => 'Active'],
            null,
            ['placeholder' => 'Choose...']
        );

        $this->assertStringContainsString('Choose...', $html);
        $this->assertStringContainsString('<option', $html);
    }

    // =========================================================================
    // Checkbox
    // =========================================================================

    public function test_checkbox_renders_with_hidden_input(): void
    {
        $html = (string) $this->form->checkbox('active', 1, true);

        // Both a hidden and a checkbox must appear
        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('name="active"', $html);
    }

    public function test_checkbox_hidden_value_is_zero(): void
    {
        $html = (string) $this->form->checkbox('active', 1, false);

        // The hidden input carries the unchecked value
        $this->assertStringContainsString('<input type="hidden" name="active" value="0">', $html);
    }

    public function test_checkbox_checked_renders_checked_attr(): void
    {
        $html = (string) $this->form->checkbox('active', 1, true);

        $this->assertStringContainsString('checked', $html);
    }

    public function test_checkbox_suppresses_hidden_input_when_no_hidden_set(): void
    {
        $html = (string) $this->form->checkbox('active', 1, true, ['no_hidden' => true]);

        $this->assertStringNotContainsString('type="hidden"', $html);
        $this->assertStringContainsString('type="checkbox"', $html);
    }

    public function test_checkbox_respects_custom_unchecked_value(): void
    {
        $html = (string) $this->form->checkbox('agree', 1, false, ['unchecked_value' => 'no']);

        $this->assertStringContainsString('value="no"', $html);
    }

    // =========================================================================
    // Radio
    // =========================================================================

    public function test_radio_renders(): void
    {
        $html = (string) $this->form->radio('role', 'admin');

        $this->assertStringContainsString('type="radio"', $html);
        $this->assertStringContainsString('value="admin"', $html);
    }

    public function test_radio_checked(): void
    {
        $html = (string) $this->form->radio('role', 'admin', true);

        $this->assertStringContainsString('checked', $html);
    }

    // =========================================================================
    // Submit / button / file
    // =========================================================================

    public function test_submit_renders(): void
    {
        $html = (string) $this->form->submit('Save', ['class' => 'btn-primary']);

        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('Save', $html);
        $this->assertStringContainsString('class="btn-primary"', $html);
    }

    public function test_button_renders(): void
    {
        $html = (string) $this->form->button('Reset', ['type' => 'reset']);

        $this->assertStringContainsString('type="reset"', $html);
        $this->assertStringContainsString('Reset', $html);
    }

    public function test_file_renders(): void
    {
        $html = (string) $this->form->file('avatar');

        $this->assertStringContainsString('type="file"', $html);
        $this->assertStringContainsString('name="avatar"', $html);
    }

    // =========================================================================
    // Label
    // =========================================================================

    public function test_label_renders(): void
    {
        $html = (string) $this->form->label('email', 'Email address');

        $this->assertStringContainsString('<label', $html);
        $this->assertStringContainsString('Email address', $html);
        $this->assertStringContainsString('for="email"', $html);
    }

    public function test_label_auto_formats_name_when_no_value(): void
    {
        $html = (string) $this->form->label('first_name');

        $this->assertStringContainsString('First Name', $html);
    }

    // =========================================================================
    // Model binding
    // =========================================================================

    public function test_model_binding_pre_populates_text_field(): void
    {
        $user = (object) ['name' => 'Bob'];

        $this->form->model($user, ['url' => '/']);
        $html = (string) $this->form->text('name');
        $this->form->close();

        $this->assertStringContainsString('value="Bob"', $html);
    }

    public function test_model_binding_works_with_array(): void
    {
        $this->form->model(['city' => 'London'], ['url' => '/']);
        $html = (string) $this->form->text('city');
        $this->form->close();

        $this->assertStringContainsString('value="London"', $html);
    }

    public function test_explicit_value_overrides_null_model_attribute(): void
    {
        $user = (object) ['name' => null];

        $this->form->model($user, ['url' => '/']);
        $html = (string) $this->form->text('name', 'Fallback');
        $this->form->close();

        // Model returns null → fall through to explicit 'Fallback'
        $this->assertStringContainsString('value="Fallback"', $html);
    }

    public function test_close_clears_model(): void
    {
        $user = (object) ['name' => 'Carol'];

        $this->form->model($user, ['url' => '/']);
        $this->form->close();

        // After close, no model → explicit value wins
        $html = (string) $this->form->text('name', 'DefaultValue');
        $this->assertStringContainsString('value="DefaultValue"', $html);
    }

    // =========================================================================
    // old() input precedence
    // =========================================================================

    public function test_old_input_takes_precedence_over_model_value(): void
    {
        $this->simulateOldInput(['username' => 'old-alice']);

        $user = (object) ['username' => 'model-alice'];

        $this->form->model($user, ['url' => '/']);
        $html = (string) $this->form->text('username');
        $this->form->close();

        $this->assertStringContainsString('value="old-alice"', $html);
    }

    public function test_old_input_takes_precedence_over_explicit_value(): void
    {
        $this->simulateOldInput(['email' => 'old@example.com']);

        $html = (string) $this->form->text('email', 'explicit@example.com');

        $this->assertStringContainsString('value="old@example.com"', $html);
    }

    public function test_old_checkbox_state_restored(): void
    {
        // User submitted form with checkbox checked
        $this->simulateOldInput(['active' => '1']);

        $html = (string) $this->form->checkbox('active', 1, false); // default: unchecked

        // old() says checked, so it should be checked despite $checked=false
        $this->assertStringContainsString('checked', $html);
    }

    public function test_absent_old_checkbox_is_unchecked_after_form_submit(): void
    {
        // Simulate: form was submitted (another field exists in old input) but the
        // 'active' checkbox was not POSTed — it should be rendered unchecked.
        $this->simulateOldInput(['name' => 'Alice']); // flash exists, but 'active' absent

        $html = (string) $this->form->checkbox('active', 1, true); // default: checked

        // old() for 'active' returns null → unchecked (flash supersedes explicit $checked)
        $this->assertStringNotContainsString('checked', $html);
    }

    // =========================================================================
    // Nested field names (bracket notation)
    // =========================================================================

    public function test_nested_field_old_input_lookup(): void
    {
        $this->simulateOldInput(['user' => ['name' => 'nested-alice']]);

        $html = (string) $this->form->text('user[name]');

        $this->assertStringContainsString('value="nested-alice"', $html);
    }

    public function test_nested_model_binding(): void
    {
        $model = ['address' => ['city' => 'Berlin']];

        $this->form->model($model, ['url' => '/']);
        $html = (string) $this->form->text('address[city]');
        $this->form->close();

        $this->assertStringContainsString('value="Berlin"', $html);
    }

    // =========================================================================
    // Macro support
    // =========================================================================

    public function test_macro_can_be_registered_and_called(): void
    {
        FormBuilder::macro('customField', function (string $label) {
            return new HtmlString("<span>{$label}</span>");
        });

        $result = (string) $this->form->customField('My Label');

        $this->assertSame('<span>My Label</span>', $result);
    }

    // =========================================================================
    // Graceful degradation for unsupported methods
    // =========================================================================

    public function test_unsupported_method_returns_empty_html_string(): void
    {
        $result = $this->form->nonExistentMethod('foo');

        $this->assertInstanceOf(HtmlString::class, $result);
        $this->assertSame('', (string) $result);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Flash old input to the session AND bind the session store to the request
     * so that Request::old() / Request::hasSession() work within the same test
     * request (Orchestra Testbench does not run StartSession middleware).
     */
    private function simulateOldInput(array $input): void
    {
        $store = $this->app['session']->driver();
        $store->flashInput($input);
        // Bind the started session store to the request singleton so that
        // $request->hasSession() returns true and $request->old() reads from it.
        $this->app['request']->setLaravelSession($store);
    }
}
