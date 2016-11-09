<?php

namespace VSAC;


use_module('backend-all');

// make sure the required directories exist
filesystem_mkdir(filesystem_files_path() . 'files');
filesystem_mkdir(filesystem_files_path() . 'desc');

backend_head('Logos and Favicons');

echo '<p class="well">This directory is a consolidated location for images and
        logos that are used by our applications.</p>';

$files = logos_list_files();

backend_display_files(router_plugin_url(), array_keys($files), $files);

if (!auth_is_authenticated()) {
    echo '<p class="well">Log in to manage files</p>';
} else {

    backend_collapsible('Remove Files', function () {
        form_form(
            array('method' => 'post', 'id' => 'remove-files'),
            function () {
                $files = array_keys(logos_list_files());
                $files = array_combine($files, $files);
                echo '<div class="row"><div class="col-sm-11">';
                form_selectbox($files, 0, '', 'remove_file', 'remove_file'); 
                echo '</div><div class="col-sm-1">';
                form_submit();
                echo '</div></div>';
            },
            function () {
                $name = basename(request_post('remove_file'));
                $file = filesystem_files_path() . 'files/' . $name;
                $desc = filesystem_files_path() . 'desc/' . $name . '.txt';
                if (!file_exists($file) || !is_file($file)) {
                    return form_flashbag('Could not find file ' . $name);
                }
                unlink($file);
                unlink($desc);
                if (file_exists($file)) {
                    return form_flashbag('Could not remove ' . $name, 'danger');
                }
                return form_flashbag('Removed ' . $name);
            }
        );
    });

    backend_collapsible('Edit file descriptions', function () {
        form_form(
            array('method' => 'post', 'id' => 'edit-files'),
            function () {
                $files = array_keys(logos_list_files());
                $files = array_combine($files, $files);
                echo '<div class="row"><div class="col-sm-3">';
                form_selectbox($files, 0, 'Change', 'edit_file', 'edit_file');
                echo '</div><div class="col-sm-8">';
                form_textbox('', 'New Description', 'edit_description', 'edit_description');
                echo '</div><div class="col-sm-1"><br>';
                form_submit();
                echo '</div></div>';
            },
            function () {
                $name = basename(request_post('edit_file'));
                $file = filesystem_files_path() . 'files/' . $name;
                $desc = filesystem_files_path() . 'desc/' . $name . '.txt';
                if (!file_exists($file)) {
                    return form_flashbag('Could not find file ' . $name, 'danger');
                }
                $description = request_post('edit_description', 'No description provided');
                file_put_contents($desc, $description);
                return form_flashbag("File {$name} updated");
            }
        );
    });

    backend_collapsible('Upload Files', function () {
        form_form(
            array('method' => 'post', 'enctype'=> "multipart/form-data"),
            function () {
                echo '<div class="row"><div class="col-sm-3">';
                form_file(config('max_file_size', 0), 'Select', 'upload_file', 'upload_file');
                echo '</div><div class="col-sm-8">';
                form_textbox('', 'Description', 'upload_description', 'upload_description');
                echo '</div><div class="col-sm-1"><br>';
                form_submit();
                echo '</div></div>';
            },
            function () {
                return form_handle_upload('upload_file', function ($tmp_name, $name) {
                    // handle the file itself
                    $destination =  filesystem_files_path() . 'files/' . $name;
                    if (file_exists($destination)) {
                        $msg = "File {$name} already exists. Delete it first to replace it.";
                        return form_flashbag('danger', $msg);
                    } elseif (!move_uploaded_file($tmp_name, $destination)) {
                        $msg = "Could not move uploaded file.";
                        return form_flashbag('danger', $msg);
                    }
                    // and the description
                    $description_path = filesystem_files_path() . 'desc/' . $name . '.txt';
                    $description = request_post(
                        'upload_description',
                        'No description provided'
                    );
                    file_put_contents($description_path, $description);
                    return form_flashbag('File uploaded');
                });

            }
        );
    });
}


backend_config_table();

backend_foot();
