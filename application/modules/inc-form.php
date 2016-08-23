<?php

/**
 * This module handles api key verification
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_config_items() */
function form_config_items()
{
    return array();
}

/** @see example_module_sysconfig() */
function form_sysconfig()
{
    return true;
}

/** @see example_module_test() */
function form_test()
{
    return true;
}

//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//

/**
 * Generate the markup for a form
 *
 * @param array $attributes attributes for the form element, action will default
 * to the current script url and method will default to GET. Any other
 * (id, class, ...) can also be set.
 * @param callable $content the callback to generate form content, will typically
 * call the control generators below.
 * @param callable $process function to call when form is submitted
 *
 * @return void
 */
function form_form(array $attributes, callable $content, callable $process = null)
{
    
    $defaults = array(
        'action' => request_url(),
        'method' => 'get',
    );
    $attributes = array_merge($defaults, $attributes);
    $attributes['id'] = form_get_id($attributes);

    $results = null;
    if (is_callable($process)
        && request_request('_form_id') === $attributes['id']
        && request_method() === $attributes['method']
    ) {
        auth_check_csrf_token();
        if ($results = call_user_func($process)) {
            if ($results == '__backend_flashbag_response') {
                echo '<script>location.replace(location.href)</script>';
                die();
            }
        }
    }

    echo '<form ';
    foreach ($attributes as $attribute => $value) {
        echo htmlspecialchars($attribute) . '="' . htmlspecialchars($value) . '" ';
    }
    echo '>';
    if (is_callable($process)) {
        form_hidden($attributes['id'], '_form_id', 'form-' . $attributes['id']);
        auth_csrf_token_input();
    }
    call_user_func($content, $results);
    echo '</form>';
}

/**
 * Create an HTML select box
 *
 * @param array $options the options to have
 * @param mixed $slected the currently selected value
 * @param string $label the control label
 * @param string $name the control name
 * @param string $id the form control id
 *
 * @return void
 */
function form_selectbox(array $options, $selected, $label, $name, $id)
{
    echo '<div class="form-group">';
    if ($label) echo sprintf(
        '<label for="%s">%s</label> ',
        htmlspecialchars($name),
        htmlspecialchars($label)
    );
    echo sprintf(
        '<select class="form-control input-sm" id="%s" name="%s">',
        htmlspecialchars($id),
        htmlspecialchars($name)
    );
    foreach ($options as $key => $value) {
        echo sprintf(
            '<option value="%s" %s>%s</option>',
            htmlspecialchars($key),
            $key === $selected ? ' selected="selected"' : '',
            htmlspecialchars($value)
        );
    }
    echo '</select></div> ';
}

/**
 * Create an HTML checkbox
 *
 * @param bool $checked the checkbox checked state
 * @param string $label the checkbox label
 * @param string $name the checkbox name
 * @param string $id the checkbox id
 *
 * @return void
 */
function form_checkbox($checked, $label, $name, $id)
{
    echo '<div class="checkbox"><label>';
    echo sprintf(
        '<input type="checkbox" name="%s" id="%s"%s value="1"> %s',
        htmlspecialchars($name),
        htmlspecialchars($id),
        $checked ? ' checked="checked"' : '',
        htmlspecialchars($label)
    );
    echo '</label></div> ';
}

/**
 * Create a text input box
 *
 * @param string $value the current value
 * @param string $label the label
 * @param string $name the control name
 * @param string $id the control id
 * @param string $type the control type
 *
 * @return void
 */
function form_textbox($value, $label, $name, $id, $type = 'text')
{
    echo '<div class="form-group">';
    if ($label) echo sprintf('<label for="%s">%s</label>', $name, $label);
    echo sprintf(
        '<input type="%s" class="form-control" id="%s" name="%s" value="%s">',
        $type,
        $id,
        $name,
        htmlspecialchars($value)
    );
    echo '</div>';
}

/**
 * Create a submit button
 *
 * @param bool $faux_label create an empty label element so the control lines
 * up with other labeled controls in the same row
 * @param string $label the text in the button
 *
 * @return void
 */
function form_submit($faux_label = false, $label = 'Submit')
{
    $class = $faux_label ? 'btn btn-block btn-default' : 'btn btn-default';
    $btn = sprintf('<button type="submit" class="%s">%s</button>', $class, $label);
    if ($faux_label) {
        $btn = sprintf('<div class="form-group"><label>&nbsp;</label>%s</div>', $btn);
    }
    echo $btn;
}

/**
 * Create a hidden form control
 * @param string $value the current value
 * @param string $name the control name
 * @param string $id the control id 
 */
function form_hidden($value, $name, $id)
{    
    echo sprintf(
        '<input type="hidden" id="%s" name="%s" value="%s">',
        $id,
        $name,
        htmlspecialchars($value)
    );
}

/**
 * Create an upload form control. Don't forget 'enctype'=>'multipart/form-data'
 * in the form options. 
 *
 * @param int $max max file size, bytes
 * @param string $label the label
 * @param string $name the control name
 * @param string $id the control id 
 */
function form_file($max, $label, $name, $id)
{
    echo '<div class="form-group">';
    form_hidden($max, 'MAX_FILE_SIZE', $id . '-max-size');
    if ($label) echo sprintf('<label for="%s">%s</label>', $name, $label);
    echo sprintf('<input type="file" id="%s" name="%s">', $id, $name);
    echo '</div>';
}

/**
 * For use in the form_form process callback, add a message to the flashbag
 * and return the result of this function to refresh the page with a flash
 * message
 */
function form_flashbag($msg, $class = 'primary')
{
    if (!fn_exists('backend_flashbag')) {
        return $msg;
    }
    backend_flashbag($class, $msg);
    return '__backend_flashbag_response';
}

/**
 * A function to facilitate processing uploading files. Will take care of
 * validation and sanitization.
 *
 * @param string $control_name The control name to process
 * @param callable $process The callback to process the file when it has
 * validated. Will receive the temp file path as the first argument and the
 * file name as the second argument.
 * @return mixed If validation fails, it will be a __flashbag_* notification
 * string, as the error will have been stored in the flashbag. Otherwise, it's
 * whatever the processing callback returned.
 */
function form_handle_upload($control_name, callable $process)
{
    $f = &superglobal('files');
    if (empty($f[$control_name])) {
        return form_flashbag('No file uploaded');
    }
    extract($f[$control_name], EXTR_SKIP); // $error, tmp_name, $name
    if (is_array($error)) {
        return form_flashbag('This function cannot process multi-uploads, use form_handle_uploads()');
    }
    return form_handle_upload_cb($error, $tmp_name, $name, $process);
}

/**
 * Like form_handle_upload, except that it works on multi-uploads.
 *
 * @param string $control_name
 * @param callable $process
 * @return array same as in form_handle_upload, except that it is an array
 * of processing return values.
 */
function form_handle_uploads($control_name, callable $process)
{
    $f = &superglobal('files');
    if (empty($f[$control_name])) {
        return form_flashbag('No file uploaded');
    }
    extract($f[$control_name], EXTR_SKIP); // $error, tmp_name, $name
    if (!is_array($error)) {
        return form_flashbag('This function processes multi-uploads, use form_handle_upload()');
    }
    $return = array();
    foreach (array_keys($error) as $key) {
        $return[] = form_handle_upload_cb($error[$key], $tmp_name[$key], $name[$key], $process);
    }
    return $return;
}

//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//


/**
 * Get a unique ID for a form
 *
 * @private
 *
 * @param array $attributes the form attributes
 *
 * @return string the id
 */
function form_get_id(array $attributes)
{
    static $ids = array();
    if (!empty($attributes['id'])) {
        $id = $attributes['id'];
        if (in_array($id, $ids)) {
            err("Form ID {$id} is not unique on this page");
        }
    } else {
        $id = '';
        foreach ($attributes as $k => $v) {
            $id .= '#' . $k . '#' . $v;
        }
        $id = md5($id);
        if (in_array($id, $ids)) {
            err("A unique ID could not be generated for this form. Set one explicitly");
        }
    }
    $ids[] = $id;
    return $id;
}

/**
 * Common file upload function use by form_handle_upload and form_handle_uploads.
 */
function form_handle_upload_cb($error, $tmp_name, $name, callable $process)
{
    $err = function ($msg) use ($name) {
        return form_flashbag('danger', "There was an error uploading {$name}: {$msg}");
    };

    switch ($error) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return $err('The uploaded file is too big');
        case UPLOAD_ERR_PARTIAL:
        case UPLOAD_ERR_NO_FILE:
            return $err('The file was not submitted or was partial.');
        default:
            return "PHP Error code: {$error}";
    }

    if (!is_uploaded_file($tmp_name)) {
        return $err('is_uploaded_file failed');
    }
    return call_user_func($process, $tmp_name, basename($name));

}

