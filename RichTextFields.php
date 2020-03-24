<?php
namespace Vanderbilt\RichTextFields;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class RichTextFields extends AbstractExternalModule
{
    function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance = 1) {
        echo $this->generateRichTextJava($project_id, $record, $instrument, $event_id, $group_id, '','',$repeat_instance);
    }

    function redcap_survey_page ($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance = 1) {
        echo $this->generateRichTextJava($project_id, $record, $instrument, $event_id, $group_id, $survey_hash,$response_id,$repeat_instance);
    }

    function generateRichTextJava($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance = 1) {
        $richTextFields = $this->getProjectSetting('rich_field');
        $currentProject = new \Project($project_id);
        $currentMeta = $currentProject->metadata;

        callJSfile('tinymce/tinymce.min.js');

        $question_by_section = $this->findQuestionBySection($project_id,$instrument);
        list ($pageFields, $totalPages) = getPageFields($instrument, $question_by_section);
        list ($saveBtnText, $hideFields, $isLastPage) = setPageNum($pageFields, $totalPages);
        if (!in_array($currentProject->table_pk,$hideFields)) {
            $hideFields[] = $currentProject->table_pk;
        }
        if (!in_array($instrument."_complete",$hideFields)) {
            $hideFields[] = $instrument."_complete";
        }

        $fieldsOnPage = array_diff($this->getFieldsOnForm($currentProject->metadata,$instrument),$hideFields);

        $validFields = array();
        $javaString = "";
        foreach ($richTextFields as $richTextField) {
            if (!in_array($richTextField,$fieldsOnPage)) continue;
            $fieldType = $currentMeta[$richTextField]['element_type'];
            $validation = $currentMeta[$richTextField]['element_validation_type'];
            if (($fieldType != "text" && $fieldType != "textarea") || $validation != "") continue;
            $validFields[] = "input[name=\"$richTextField\"]";
        }
        if (!empty($validFields)) {
            $javaString = "<script>
            $(document).ready(function() {
                tinymce.init({
                    selector: '".implode(",",$validFields)."',
                    height: 350,
                    branding: false,
                    statusbar: true,
                    menubar: false,
                    elementpath: false, // Hide this, since it oddly renders below the textarea.
                    plugins: ['paste autolink lists link searchreplace code fullscreen table directionality'],
                    toolbar1: 'bold italic link | alignleft aligncenter alignright alignjustify | table',
                    toolbar2: 'bullist numlist outdent indent | forecolor backcolor | removeformat | undo redo',
                    contextmenu: \"copy paste | link image inserttable | cell row column deletetable\",
                    relative_urls: false,
                    convert_urls : false,
                    convert_fonts_to_spans: true,
                    paste_word_valid_elements: \"b,strong,i,em,h1,h2,u,p,ol,ul,li,a[href],span,color,font-size,font-color,font-family,mark,table,tr,td\",
                    paste_retain_style_properties: \"all\",
                    paste_postprocess: function(plugin, args) {
                        args.node.innerHTML = cleanHTML(args.node.innerHTML);
                    },
                    remove_linebreaks: true,
                    content_style: 'body { font-weight: bold; }', // Match REDCap's default bold label style.
                    formats: {
                        bold: {
                            inline: 'span',
                            styles: {
                                'font-weight': 'normal'  // Make the 'bold' option function like an 'unbold' instead.
                            }
                        }
                    }
                });
            });
            </script>";
        }
        return $javaString;
    }

    function findQuestionBySection($project_id,$instrument) {
        $result = $this->query("SELECT question_by_section FROM redcap_surveys WHERE project_id=? AND form_name=?",[$project_id,$instrument]);
        return $result->fetch_assoc()['question_by_section'];
    }

    function getFieldsOnForm($metadata, $formname) {
        $fieldList = array();

        foreach ($metadata as $fieldName => $fieldInfo) {
            if ($fieldInfo['form_name'] == $formname) {
                $fieldList[] = $fieldName;
            }
        }
        return $fieldList;
    }
}