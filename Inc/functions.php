<?php

/**
 * @package  Directorist - Export Import Taxonomies
 */

//ATBDP_DIRECTORY_TYPE

function directorist_save_uploaded_taxonomy_from_csv($taxonomy_name, $csvFile)
{

    $label = array();
    $x = 0;
    $inserted_terms = array();
    $taxonomy_name_o = 'taxonomy';
    if ($taxonomy_name == 'at_biz_dir-category') $taxonomy_name_o = 'category';
    if ($taxonomy_name == 'at_biz_dir-location') $taxonomy_name_o = 'location';

    $dir_types = dir_exim_get_directory_types();

    while (($data = fgetcsv($csvFile)) !== FALSE) {
        if (!empty($data) && count($data) > 0) {
            if ($x == 0) {
                $label = $data;
            } else {
                $tax_data = array();
                foreach ($data as $key => $value) {
                    if ($key == 0) {
                        $tax_data['name'] = $value;
                    } else {
                        $tax_data[$label[$key]] = $value;
                    }
                }


                if (count($tax_data) > 0) {
                    if (!term_exists($tax_data['name'], $taxonomy_name)) {
                        $term_args = array();
                        $term_args['parent'] = 0;

                        /**
                         * Deal With Parent
                         */
                        if (isset($tax_data['parent']) && !empty($tax_data['parent'])) {
                            if (isset($inserted_terms[$tax_data['parent']]) && !empty($inserted_terms[$tax_data['parent']])) {
                                $term_args['parent'] = $inserted_terms[$tax_data['parent']];
                            } else {
                                $parent_term = get_term_by('name', $tax_data['parent'], $taxonomy_name);
                                if ($parent_term) {
                                    $term_args['parent'] = $parent_term->term_id;
                                }
                            }
                        }

                        /**
                         * Deal with Directory Types
                         */
                        $term_directory_types = array();
                        if (!empty($dir_types) && isset($tax_data['directory_type']) && !empty($tax_data['directory_type'])) {
                            $directory_types = explode(',', $tax_data['directory_type']);

                            if (!empty($directory_types) && count($directory_types) > 0) {
                                foreach ($directory_types as $directory_type) {
                                    if (!empty($dir_types && count($dir_types) > 0)) {
                                        foreach ($dir_types as $key => $type) {
                                            if ($type === trim($directory_type)) $term_directory_types[] = $key;
                                        }
                                    }
                                }
                            }
                        }


                        if (isset($tax_data['description']) && !empty($tax_data['description'])) $term_args['description'] = $tax_data['description'];
                        if (isset($tax_data['slug']) && !empty($tax_data['slug'])) $term_args['slug'] = $tax_data['slug'];

                        $term_data = wp_insert_term(
                            $tax_data['name'],   // the term 
                            $taxonomy_name, // the taxonomy
                            $term_args
                        );

                        if (!is_wp_error($term_data) && isset($term_data['term_id']) && !empty($term_data['term_id'])) {
                            $inserted_terms[$tax_data['name']] = $term_data['term_id'];
                            // Icon
                            if ($taxonomy_name == 'at_biz_dir-category' && isset($tax_data['icon']) && !empty($tax_data['icon'])) update_term_meta($term_data['term_id'], 'category_icon', $tax_data['icon']);
                            // Directory Type
                            if (!empty($term_directory_types) && count($term_directory_types) > 0) update_term_meta($term_data['term_id'], '_directory_type', $term_directory_types);
                            // Latitude
                            if (isset($tax_data['latitude']) && !empty($tax_data['latitude'])) update_term_meta($term_data['term_id'], 'latitude', $tax_data['latitude']);
                            // Longitude
                            if (isset($tax_data['longitude']) && !empty($tax_data['longitude'])) update_term_meta($term_data['term_id'], 'longitude', $tax_data['longitude']);
                        }
                    }
                }
            }
        }
        $x++;
    }

    //e_var_dump($label);
    //e_var_dump($inserted_terms);

    echo "<h4 style='color: green;'>Total " . $taxonomy_name_o . " inserted : " . count($inserted_terms) . "</h4>";
}


// CSV EXPORT

if (isset($_GET['action']) && $_GET['action'] == 'download_csv') {
    // Handle CSV Export
    add_action('admin_init', 'direcorist_taxonomy_csv_export');
}

function direcorist_taxonomy_csv_export()
{

    // Check for current user privileges 
    if (!current_user_can('manage_options')) {
        return false;
    }

    // Check if we are in WP-Admin
    if (!is_admin()) {
        return false;
    }

    // Nonce Check
    $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
    if (!wp_verify_nonce($nonce, 'download_csv')) {
        die('Security check error');
    }

    $taxonomy_name = isset($_GET['taxonomy']) && !empty($_GET['taxonomy']) ? $_GET['taxonomy'] : 'at_biz_dir-category';

    ob_start();

    $domain = $_SERVER['SERVER_NAME'];

    if ($taxonomy_name == 'at_biz_dir-category') {
        $header_row = array("name", "slug", "icon", "description", "parent", "directory_type");
        $filename = 'categories.csv';
    } else if ($taxonomy_name == 'at_biz_dir-location') {
        $header_row = array("name", "slug", "description", "parent", "directory_type");
        $filename = 'locations.csv';
    }

    $dir_types = dir_exim_get_directory_types();

    $data_rows = array();

    $term_list = get_terms(array(
        'taxonomy' => $taxonomy_name,
        'hide_empty' => false,
        'orderby' => 'parent',
    ));

    if ($term_list && count($term_list) > 0) {
        foreach ($term_list as $term) {

            //Deal with parent
            $term_parent = '';
            if ($term->parent) {
                $parent_term = get_term_by('id', $term->parent, $taxonomy_name);
                if ($parent_term) {
                    $term_parent = wp_specialchars_decode($parent_term->name);
                }
            }

            //Deal with directory type
            $term_dir_types = array();
            $directory_types = get_term_meta($term->term_id, '_directory_type', true) ? get_term_meta($term->term_id, '_directory_type', true) : '';

            if (!empty($dir_types) && !empty($directory_types)) {
                foreach ($directory_types as $directory_type) {
                    $term_dir_types[] = $dir_types[$directory_type];
                }
            }

            $term_dir_types = count($term_dir_types) > 0 ? implode(',', $term_dir_types) : '';

            //Deal with Icon
            if ($taxonomy_name == 'at_biz_dir-category') {
                $term_icon = get_term_meta($term->term_id, 'category_icon', true) ? get_term_meta($term->term_id, 'category_icon', true) : '';
                $row = array(
                    wp_specialchars_decode($term->name),
                    wp_specialchars_decode($term->slug),
                    $term_icon,
                    wp_specialchars_decode($term->description),
                    $term_parent,
                    $term_dir_types
                );
            } else {
                $row = array(
                    wp_specialchars_decode($term->name),
                    wp_specialchars_decode($term->slug),
                    wp_specialchars_decode($term->description),
                    $term_parent,
                    $term_dir_types
                );
            }

            $data_rows[] = $row;
        }
    }

    $fh = @fopen('php://output', 'w');
    fprintf($fh, chr(0xEF) . chr(0xBB) . chr(0xBF));
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Content-Description: File Transfer');
    header('Content-type: text/csv');
    header("Content-Disposition: attachment; filename={$filename}");
    header('Expires: 0');
    header('Pragma: public');
    fputcsv($fh, $header_row);

    foreach ($data_rows as $data_row) {
        fputcsv($fh, $data_row);
    }
    fclose($fh);

    ob_end_flush();

    die();
}


function dir_exim_get_directory_types()
{
    $dir_types = array();
    $directory_types = get_terms(array(
        'taxonomy'   => ATBDP_TYPE,
        'hide_empty' => false,
    ));

    if (!is_wp_error($directory_types) && count($directory_types) > 0) {
        foreach ($directory_types as $dir_type) {
            $dir_types[$dir_type->term_id] = $dir_type->slug;
        }
    }

    if (!empty($dir_types)) {
        return $dir_types;
    } else {
        return false;
    }
}


//add_action('admin_footer', 'directorist_taxonomy_ajax_import');

function directorist_taxonomy_ajax_import()
{
?>
    <script type="text/javascript">
        jQuery(document).ready(($) => {
            $('#exim_import_form').on('submit', function(event) {
                event.preventDefault();
                var form_data = new FormData(this)

                for (let [name, value] of form_data) {
                    //console.log(`${name} = ${value}`); // key1 = value1, then key2 = value2
                    //console.log(value)
                    if (name === 'taxonomy_file') {
                        var formattedArray = readCSVFile(value)
                    }
                }

            })

            function readCSVFile(file) {

                if (file) {

                    // FileReader Object
                    var reader = new FileReader();

                    // Read file as string 
                    reader.readAsText(file);

                    // Load event
                    reader.onload = function(event) {

                        var newArray = [];

                        var header = [];

                        // Read file data
                        var csvdata = event.target.result;

                        // Split by line break to gets rows Array
                        var rowData = csvdata.split('\n');

                        header = rowData[0].split(',');

                        rowData.forEach((rowColData, key) => {
                            if (key !== 0 && rowColData !== '') {

                                var firstSplit = rowColData.split(',"');
                                var secondSplit = firstSplit[0].split(',');
                                console.log(firstSplit)
                                var rowColData = firstSplit[0].split(',');

                                var rowInfo = []
                                rowColData.forEach((rowCol, rowColkey) => {
                                    rowInfo[header[rowColkey]] = rowCol;
                                })
                                if (firstSplit[1] !== '') rowInfo['description'] = '"' + firstSplit[1];
                                if (rowInfo.name !== '') newArray.push(rowInfo);
                            }
                        });

                        //console.log(newArray)
                        //if (newArray.length > 0) executeImport(newArray)

                        return newArray;
                    };

                } else {
                    alert("Please select a file.");
                }

            }

            function executeImport(newArray) {
                var count = 0;
                var limit = 10;
                var start = 0;
                var count = newArray.length;
                var end, selectedListings;

                var exim_nonce = $('#exim_nonce').val();

                (function loop() {
                    setTimeout(function() {
                        // Do something here
                        end = start + limit;
                        console.log('Start: ' + start + ' - End: ' + end);
                        selectedListings = newArray.slice(start, end);

                        if (selectedListings.length > 0) {
                            var arrayListing = processData(selectedListings)
                            //console.log(arrayListing)
                            //console.log(JSON.stringify(arrayListing));
                            exim_import_request(arrayListing, exim_nonce);
                        }

                        if (start > 10) {
                            return;
                        }
                        start = end;
                        // Call the loop function again after a delay
                        loop();
                    }, 1000);
                })();
            }

            function processData(selectedListings) {
                let main = []
                selectedListings.forEach(taxonomy => {
                    const object = Object.assign({}, taxonomy);
                    main.push(object)
                })
                return main;
            }

            function exim_import_request(taxonomies, exim_nonce) {
                $.ajax({
                    type: "post",
                    dataType: "json",
                    url: directorist_admin.ajaxurl,
                    data: {
                        action: "exim_import_request",
                        nonce: exim_nonce,
                        taxonomies: taxonomies
                    },
                    success: function(response) {
                        if (response.type == "success") {
                            console.log(response)
                        } else {
                            alert("Your vote could not be added")
                        }
                    }
                });
            }

        })
    </script>
<?php
}

add_action("wp_ajax_exim_import_request", function () {
    if (!wp_verify_nonce($_REQUEST['nonce'], "exim_import_nonce")) {
        exit("No naughty business please");
    }

    $taxonomies = isset($_REQUEST['taxonomies']) && !empty($_REQUEST['taxonomies']) ? $_REQUEST['taxonomies'] : [];

    if (count($taxonomies) > 0) {
        foreach ($taxonomies as $taxonomy) {
            add_taxonomy_data($taxonomy);
        }
    }

    echo json_encode(array('type' => 'success', 'taxonomies' => $_REQUEST['taxonomies']));
    die();
});


function add_taxonomy_data($tax_data)
{
    $inserted_terms = array();
    $taxonomy_name = ATBDP_LOCATION;
    $result = false;

    if (count($tax_data) > 0) {

        $is_exist = term_exists($tax_data['name'], $taxonomy_name);

        if (isset($is_exist['term_id']) && empty($is_exist['term_id'])) {
            // if (isset($tax_data['description']) && !empty($is_exist['term_id'])) {
            //     global $wpdb;
            //     $table_name = $wpdb->prefix . 'term_taxonomy';
            //     $result = $wpdb->update(
            //         $table_name,
            //         array('description' => $tax_data['description']),
            //         array('term_id' => $is_exist['term_id'])
            //     );
            //     update_term_meta($is_exist['term_id'], '_description', $tax_data['description']);
            // }
        } else {
            $term_args = array();
            $term_args['parent'] = 0;

            /**
             * Check if parent exist
             */
            if (isset($tax_data['parent']) && !empty($tax_data['parent'])) {
                $parent_exists = term_exists($tax_data['parent'], $taxonomy_name);
                if (!$parent_exists) return false;
            }

            /**
             * Deal With Parent
             */
            if (isset($tax_data['parent']) && !empty($tax_data['parent'])) {
                if (isset($inserted_terms[$tax_data['parent']]) && !empty($inserted_terms[$tax_data['parent']])) {
                    $term_args['parent'] = $inserted_terms[$tax_data['parent']];
                } else {
                    $parent_term = get_term_by('name', $tax_data['parent'], $taxonomy_name);
                    if ($parent_term) {
                        $term_args['parent'] = $parent_term->term_id;
                    }
                }
            }

            /**
             * Deal with Directory Types
             */
            $dir_types = dir_exim_get_directory_types();
            $term_directory_types = array();
            if (!empty($dir_types) && isset($tax_data['directory_type']) && !empty($tax_data['directory_type'])) {
                $directory_types = explode(',', $tax_data['directory_type']);
                if (!empty($directory_types) && count($directory_types) > 0) {
                    foreach ($directory_types as $directory_type) {
                        if (!empty($dir_types && count($dir_types) > 0)) {
                            foreach ($dir_types as $key => $type) {
                                //file_put_contents(dirname(__FILE__) . '/dir.json', json_encode(array($type, $directory_type)));
                                if ($type === trim($directory_type)) $term_directory_types[] = $key;
                            }
                        }
                    }
                }
            }




            if (isset($tax_data['description']) && !empty($tax_data['description'])) $term_args['description'] = $tax_data['description'];
            if (isset($tax_data['slug']) && !empty($tax_data['slug'])) $term_args['slug'] = $tax_data['slug'];

            $term_data = wp_insert_term(
                $tax_data['name'],   // the term 
                $taxonomy_name, // the taxonomy
                $term_args
            );

            if (is_wp_error($term_data)) {
                return false;
            }

            if (!is_wp_error($term_data) && isset($term_data['term_id']) && !empty($term_data['term_id'])) {
                $inserted_terms[$tax_data['name']] = $term_data['term_id'];
                // Icon
                if ($taxonomy_name == 'at_biz_dir-category' && isset($tax_data['icon']) && !empty($tax_data['icon'])) update_term_meta($term_data['term_id'], 'category_icon', $tax_data['icon']);
                // Directory Type
                if (!empty($term_directory_types) && count($term_directory_types) > 0) update_term_meta($term_data['term_id'], '_directory_type', $term_directory_types);
                // Latitude
                if (isset($tax_data['latitude']) && !empty($tax_data['latitude'])) update_term_meta($term_data['term_id'], 'latitude', $tax_data['latitude']);
                // Longitude
                if (isset($tax_data['longitude']) && !empty($tax_data['longitude'])) update_term_meta($term_data['term_id'], 'longitude', $tax_data['longitude']);
            }

            $result = true;
        }
    }
    return $result;
}


function directorist_custom_update_location($post)
{
    //file_put_contents(dirname(__FILE__) . '/file.json', json_encode($post));
    return add_taxonomy_data($post);
}
