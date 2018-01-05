<?php
class wacimportcsv{

    public $_list_save_name = "wac_list_save_name_importcsv";
    private $_html_admin;
    private $_message_post_process;
    private $_wacmetakey = "yd_csv_import_line_id";

    public function __construct() {

        /**ADMIN**/
        if(!is_admin()){
            return;
        }
        add_action('admin_menu', array($this, 'importcsv_menu'));
        add_action('admin_init', 'fontawesome_dashboard');
        
        add_action ('init',array($this,'process_post'));

        /** add js **/
        add_action('admin_enqueue_scripts', array($this,'wacimportcsv_scripts_enqueue'));
        /** add css **/
        add_action('admin_enqueue_scripts', array($this,'wacimportcsv_css_enqueue'));

        /** ajax **/
        add_action( 'wp_ajax_wac_editcsvdocument', array($this,'wac_editcsvdocument') );
        add_action( 'wp_ajax_nopriv_wac_editcsvdocument', array($this,'wac_editcsvdocument') );

        add_action( 'wp_ajax_wac_deletecsvdocument', array($this,'wac_deletecsvdocument') );
        add_action( 'wp_ajax_nopriv_wac_deletecsvdocument', array($this,'wac_deletecsvdocument') );
    }

    public function wacimportcsv_scripts_enqueue(){
        wp_enqueue_script('jquery');
        wp_enqueue_script('wacreadcsvdocument', plugins_url('/js/wac_importcsv_admin.js', dirname(__FILE__)), array('jquery'), '0.0.1', false);
    }
    public function wacimportcsv_css_enqueue(){
        wp_register_style('style_csv', plugins_url('/css/style_csv.css', dirname(__FILE__)), array(), '1.0', 'all');
        wp_enqueue_style('style_csv'); // Enqueue it!
    }

    public function fontawesome_dashboard() {
        wp_enqueue_style('fontawesome', 'http:////netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css', '', '4.0.3', 'all');
    }


    public function read_csv($file,$startingline,$separatortype=",",$limitline = 0){

        $return_array = array();
        $row_count = 0;
        if (($handle = fopen($file, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 0, $separatortype)) !== FALSE) {

                //zap x lines
                if($startingline!=0 && $row_count<$startingline){
                    $row_count++;
                    continue;
                }

                $num = count($data);
                //echo "<p> $num champs à la ligne $row_count: X <br /></p>\n";
                for ($c=0; $c < $num; $c++) { //one line
                    //echo $data[$c] . "<br />\n";
                    $return_array[$row_count][] = $data[$c];
                }
                //stop nomber lines
                $row_count++;
                if($limitline!="all" && $limitline<=$row_count){
                    break;
                }
            }
            fclose($handle);
        }        
        return $return_array;
    }

    public function process_post(){

        if(isset($_FILES["wacfilecsv"]["tmp_name"]) && $_FILES["wacfilecsv"]["tmp_name"]!=""){
            //get the options
            $list_urls = get_option($this->_list_save_name,false);
            $list_decoded = array();
            if($list_urls){
                $list_decoded = json_decode($list_urls,true);
            }            

            //get first line of csv
            $data_first_line = $this->read_csv($_FILES["wacfilecsv"]["tmp_name"],$_POST['startline'],$_POST['separatortype'],0);   

            /* STARTING LINE */
            $list_decoded[$_POST['namesauvegarde']]['startline'] = $_POST['startline'];
            /* SAVE AUTHOR */
            $list_decoded[$_POST['namesauvegarde']]['author'] = $_POST['authorsave'];
            /* SAVE CPT */
            $list_decoded[$_POST['namesauvegarde']]['cpt'] = $_POST['cptsave'];
            /* save first line */
            $list_decoded[$_POST['namesauvegarde']]['firstline'] = $data_first_line[0];
            /* save first line */
            $list_decoded[$_POST['namesauvegarde']]['separatortype'] = $_POST['separatortype'];
            /* save first line */
            $list_decoded[$_POST['namesauvegarde']]['actionligneabsente'] = $_POST['actionligneabsente'];

            /*reencode */
            $tosave = json_encode($list_decoded);
            /*save*/
            update_option($this->_list_save_name,$tosave);

            //NEXT PART         
            $this->selectorfields($data_first_line[0],$_POST['cptsave'],$_POST['namesauvegarde']);
        }

        if(isset($_POST['associatecptcolumn']) && $_POST['associatecptcolumn']!=""){

            $alldata = $_POST;
            $name_index = $_POST['namesauvegarde'];
            unset($alldata['associatecptcolumn']);
            unset($alldata['namesauvegarde']);

            $to_save_data = array();
            foreach($alldata as $idcol=>$data){
                $id_data = explode('|',$idcol);
                //TODO SECURIT2 POUR PPL
                //for taxo
                if(preg_match('#taxo_#', $id_data[0])){
                    //[language][taxo][id col] = data
                    $id_data[0] = str_replace('taxo_','', $id_data[0]);
                    $to_save_data[$id_data[1]]['taxonomie'][$id_data[0]] = $data;

                }elseif(preg_match('#subt_#', $id_data[0])){
                    //[language][taxo][id col] = data
                    $id_data[0] = str_replace('subt_','', $id_data[0]);
                    $to_save_data[$id_data[1]]['subtaxonomie'][$id_data[0]] = $data;

                }else{
                    //[language][id col] = data
                    $to_save_data[$id_data[1]][$id_data[0]] = $data;
                } 
            }

            /* get urls */
            $list_urls = get_option($this->_list_save_name,false);
            $list_decoded = array();
            if($list_urls){
                $list_decoded = json_decode($list_urls,true);
            }
            $list_decoded[$name_index]['association'] = $to_save_data;
            /*reencode */
            $tosave = json_encode($list_decoded);
            /*save*/
            update_option($this->_list_save_name,$tosave);
        }

        /* TEST IMPORT */
        if(isset($_FILES['wacfilecsvprocess']["tmp_name"]) && $_FILES['wacfilecsvprocess']["tmp_name"]!=""){
            $this->import_data_from_csv($_FILES['wacfilecsvprocess']["tmp_name"],$_POST['wacfilecsv_namesave']);
        }        
    }    

    public function selectorfields($titles,$cptlinked,$namesauvegarde,$association_list){

        $html = '';

        $_ppl_names = pll_languages_list(array('fields'=>'name'));
        $_ppl_slugs = pll_languages_list(array('fields'=>'slug'));

        $html.= '<hr>';
        $html.= '<h2>Modifier le modèle d\'importation</h2>';
        $html.= '<div><strong>'.$namesauvegarde.'</strong></div>';
        $html.= '<form action="" method="POST" enctype="multipart/form-data">';
        $html.= '<div>';

        if(isset($cptlinked) && $cptlinked != ""){
            $args = array(
                'posts_per_page'   => 1,
                'post_type'        => $cptlinked,
                'post_status'      => "any",
                'order'   => 'ASC',
                'orderby' =>  'ID'
            );
            $cptposts = get_posts( $args );            
            if(isset($cptposts[0]->ID) && $cptposts[0]->ID != ""){
                //will be used later to creafte select fields
                $fields_acf = get_field_objects($cptposts[0]->ID);
            }
        }

        $html.= '<form action="" method="POST">';
        $html.= '<table>';

        $html.= '<thead>';
        $html.= '<tr>';
        $html.= '<th>';
        $html.= 'Méta-données';
        $html.= '</th>';

        foreach($_ppl_slugs as $keyppl=>$ppl_slug){
            $html.= '<th>';
            $html.= $ppl_slug;
            $html.= '</th>';
        }

        $html.= '</tr>';
        $html.= '</thead>';
        $html.= '<tbody>';

        $color_background = '#dcdcdc';
        $color_set = 1;

        //////////////////
        //Add basic fields
        //////////////////
        $array_list_fields_wp = array(
            'id_unique'=>'Colonne identifiante',
            'post_title'=>'Post titre',
            'post_content'=>'Post content',
            'post_category'=>'Post catégorie'
        );
        foreach($array_list_fields_wp as $fieldkey=>$fieldname){
            if($color_set){
                $color_set = 0;
                $color = 'background-color: '.$color_background.';';
            }else{
                $color_set = 1;
                $color = '';
            }
            //LINE -------------------------
            $html.= '<tr style="'.$color.'">';

            //TITLE COL --------------------------
            $html.= '<td>';
            $html.= '<strong style="width: 250px;display: inline-block;">';
            $html.= $fieldname;
            $html.= '</strong>';
            $html.= '</td>';

            //COL BY LANGUAGE --------------------
            foreach($_ppl_names as $keyppl=>$ppl_name){
                $html.= '<td>';
                $default_value = "notselected";
                if($association_list[$_ppl_slugs[$keyppl]][$fieldkey] !== null){
                    $default_value = $association_list[$_ppl_slugs[$keyppl]][$fieldkey];
                }
                $html.= $this->create_select_form($titles, $fieldkey.'|'.$_ppl_slugs[$keyppl],$default_value);

                //                    $default_value = "";
                //                    if($association_list[$fieldkey.'_text'] !== null && $fieldkey!='id_unique'){
                //                       $default_value = $association_list[$fieldkey.'_text'];
                //                    }

                //                    if($fieldkey!='id_unique'){
                //                        $html.= '<input type="text" name="'.$fieldkey.'_text_'.$_ppl_slugs[$fieldkey].'" value="'.$default_value.'">';
                //                    }else{
                //                        $html.= "Si non associé, un identifiant unique sera automatiquement généré";
                //                    }
                $html.= '</td>';
            }

            $html.= '</tr>';             
        }

        /////////////////
        //Add Post TAXOS
        /////////////////
        //get
        $all_taxos = get_object_taxonomies($cptlinked);
        foreach($all_taxos as $taxo_assoc){
            if($taxo_assoc === "language" || $taxo_assoc === "post_translations"){
                continue;
            }

            $data_taxonomy = get_taxonomy($taxo_assoc);


            //TODO SECURIT21 SI PAS PPL
            //get the default
            if($color_set){
                $color_set = 0;
                $color = 'background-color: '.$color_background.';';
            }else{
                $color_set = 1;
                $color = '';
            }
            //LINE -------------------------
            $html.= '<tr style="'.$color.'">';

            //TITLE COL --------------------------
            $html.= '<td>';
            $html.= '<strong style="width: 250px;display: inline-block;">';
            $html.= 'TAXO : '.$taxo_assoc;
            $html.= '</strong>';
            $html.= '</td>';

            //COL BY LANGUAGE --------------------
            foreach($_ppl_slugs as $keyppl=>$ppl_name){

                $html.= '<td>';
                $default_value = "notselected";
                if($association_list[$_ppl_slugs[$keyppl]]['taxonomie'][$taxo_assoc] !== null){
                    $default_value = $association_list[$_ppl_slugs[$keyppl]]['taxonomie'][$taxo_assoc];
                }
                $html.= $this->create_select_form($titles, "taxo_".$taxo_assoc.'|'.$_ppl_slugs[$keyppl],$default_value);
                $html.= '</td>';
            }
            $html.= '</tr>'; 


            if(isset($data_taxonomy->hierarchical) && $data_taxonomy->hierarchical==1){

                $html.= '<tr style="'.$color.'">'; 
                //TITLE COL --------------------------
                $html.= '<td>';
                $html.= '<strong style="width: 250px;display: inline-block;">';
                $html.= 'SOUS TAXO : '.$taxo_assoc;
                $html.= '</strong>';
                $html.= '</td>';

                foreach($_ppl_slugs as $keyppl=>$ppl_name){
                    //create sous rubrique
                    $html.= '<td>';
                    $default_value = "notselected";
                    if($association_list[$_ppl_slugs[$keyppl]]['subtaxonomie'][$taxo_assoc] !== null){
                        $default_value = $association_list[$_ppl_slugs[$keyppl]]['subtaxonomie'][$taxo_assoc];
                    }
                    $html.= $this->create_select_form($titles, "subt_".$taxo_assoc.'|'.$_ppl_slugs[$keyppl],$default_value);
                    $html.= '</td>';  
                }

                $html.= '</tr>'; 
            }


        }

        /////////////////
        //Add Post status
        /////////////////
        if($color_set){
            $color_set = 0;
            $color = 'background-color: '.$color_background.';';
        }else{
            $color_set = 1;
            $color = '';
        }
        //LINE -------------------------
        $html.= '<tr style="'.$color.'">';

        //COL TITLE ----------------
        $html.= '<td>';
        $html.= '<strong style="width: 250px;display: inline-block;">';
        $html.= "Post status";
        $html.= '</strong>';
        $html.= '</td>';

        //COL ----------------------
        foreach($_ppl_names as $keyppl=>$ppl_name){
            $html.= '<td>';
            $list_status = array(
                'publish'=>'Publié',
                'draft'=>'Brouillon'
            );
            $default_value = "notselected";
            if($association_list[$_ppl_slugs[$keyppl]]['post_status'] !== null){
                $default_value = $association_list[$_ppl_slugs[$keyppl]]['post_status'];
            }
            $html.= $this->create_select_form($list_status, 'post_status|'.$_ppl_slugs[$keyppl],$association_list[$_ppl_slugs[$keyppl]]['post_status']);
            $html.= '</td>';
        }

        $html.= '</tr>';

        ////////////////
        //Add acf fields
        ////////////////
        foreach( $fields_acf as $field_slug => $field_data ){
            if($color_set){
                $color_set = 0;
                $color = 'background-color: '.$color_background.';';
            }else{
                $color_set = 1;
                $color = '';
            }
            //LINE--------------------------
            $html.= '<tr style="'.$color.'">';

            //COL ----------------------
            $html.= '<td>';
            $html.= '<strong style="width: 250px;display: inline-block;">';
            $html.= $field_data['label'];
            $html.= '</strong>';
            $html.= '<div><span>'.$field_data['name'].'</span></div>';
            $html.= '</td>';

            //COL-----------------------
            foreach($_ppl_names as $keyppl=>$ppl_name){
                $html.= '<td>';
                $default_value = "notselected";
                if($association_list[$_ppl_slugs[$keyppl]][$field_data['key']] !== null){
                    $default_value = $association_list[$_ppl_slugs[$keyppl]][$field_data['key']];
                }

                $html.= $this->create_select_form($titles, $field_data['key'].'|'.$_ppl_slugs[$keyppl],$default_value);

                $default_value = "";
                //                        if($association_list[$field_data['key'].'_text'] !== null){
                //                           $default_value = $association_list[$field_data['key'].'_text'];
                //                        }

                //$html.= '<input type="text" name="'.$field_data['key'].'_text" value="'.$default_value.'">';
                $html.= '</td>';
            }

            $html.= '</tr>';
        }

        $html.= '</tbody>';
        $html.= '</table>';
        $html.= '</div>';
        $html.= '<input value="'.$namesauvegarde.'" name="namesauvegarde" type="hidden">';
        $html.= '<input value="1" name="associatecptcolumn" type="hidden">';
        $html.= '<input type="submit" value="Mettre à jour le modèle">';
        $html.= '</form>';


        /* for when used in form */
        $this->_html_admin = $html;
        /* for when used by ajax */
        return $html;
    }

    public function importcsv_menu(){
        $page_title = 'CSV to WP';
        $menu_title = 'CSV to WP';
        $capability = 'manage_options';
        $menu_slug = 'wacimportcsvoptions';
        $function = array($this, 'wacimportcsvoptions_main_menu_options');
        $icon_url = 'fas fa-upload';

        add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function, $icon_url);
    }

    public function import_data_from_csv($file,$key){

        //get data from key
        $list_urls = get_option($this->_list_save_name,false);
        $list_decoded = json_decode($list_urls,true);
        $data_save = $list_decoded[$key];

        $startline = $data_save['startline']+1;
        $values = $this->read_csv($file,$startline,$data_save['separatortype'],"all");

        //get all the keys to check witch one exist already
        global $wpdb;

        $query_postmeta = "SELECT meta_value,post_id FROM `".$wpdb->prefix."postmeta` WHERE `meta_key` = '".$this->_wacmetakey."'";
        $result_postmeta = $wpdb->get_results($query_postmeta,ARRAY_A);
        $postmeta_list = array();
        foreach($result_postmeta as $value_postmeta){
            $postmeta_list[$value_postmeta['meta_value']] = $value_postmeta['post_id'];
        }

        //update post meta "yd_csv_import_line_id"
        $association_list = $data_save['association'];
        $id_postmeta = $association_list['id_unique']; 

        $ajout_post = 0;
        $delete_post = 0;
        //parse csvlines if no $this->_wacmetakey exist, create post
        $message_lines = array();
        foreach($values as $line){

            if($id_postmeta != "notselected"){
                $unique_id_value = md5($line[$id_postmeta]);
            }else{
                $unique_id_value = md5(implode('|',$line));
            }

            if(isset($postmeta_list[$unique_id_value]) && $postmeta_list[$unique_id_value]!=""){
                unset($postmeta_list[$unique_id_value]); //virer du tableau ceux qui on été trouvé pour finir avec ceux qui n'ont pas été ajoutés
                continue;
            }else{
                unset($postmeta_list[$unique_id_value]); //virer du tableau ceux qui on été trouvé pour finir avec ceux qui n'ont pas été ajoutés
                $message_lines = $this->create_post($line,$list_decoded,$key,$unique_id_value);
                $ajout_post++;
            }

            //break;//ONLY FOR TEST
        }

        //mettre le post en trash si l'option a été choisi que le postmeta list a encore des elements et le post meta est correspondant
        //ignorerpost ou deletepost
        if($data_save['actionligneabsente'] == "deletepost" && count($postmeta_list)>0){ 
            foreach($postmeta_list as $metadata=>$idpost){
                //test si le post est du bon cpt
                $posttype = get_post_type($idpost);
                if($posttype == $data_save['cpt']){
                    wp_trash_post($idpost);
                    $delete_post++;
                }
            }                
        }//end if trash

        $this->_message_post_process = $ajout_post.' ont été ajoutés.<br>'.$delete_post.' ont été supprimés.';

        //mail
        $user_meta = get_userdata(get_current_user_id());
        $to = $user_meta->user_email;
        $subject = "Informations sur l'import du ".date('d-m-Y H:i:s');

        $message = $ajout_post.' ont été ajoutés.<br>';
        $message.= $delete_post.' ont été supprimés.<br>';
        foreach($message_lines as $idpostml=>$ml){
            $url = get_edit_post_link($idpostml);
            $message.= "Pour le post : ".get_the_title($idpostml)." <a href='".$url."'>$url</a><br>";
            $message.= implode('',$ml);
        }        
        $t = wp_mail($to,$subject,$message);
    }

    public function create_post($line,$list_decoded,$key,$unique_id_value){   

        $message = array();
        $association_list_language = $list_decoded[$key]['association'];

        //loop for language
        $list_assoc_language = array();
        foreach($association_list_language as $language_slug=>$association_list){

            $data = array();
            //create a post
            $list_acf = array();
            foreach($association_list as $key_al => $value_al){
                if(isset($value_al) && ($value_al == "notselected" || $value_al=="" ) ){
                    continue;
                }
                //$association_list[$key_al] gives the key to seek
                $data[$key_al] = $line[$value_al];

                if(preg_match('#field_#', $key_al)){
                    $key_al = str_replace('_text', '', $key_al);
                    $list_acf[$key_al] = $line[$value_al];
                }
            }

            //add post status
            $data['post_status'] = $association_list['post_status'];
            $data['post_type'] = $list_decoded[$key]['cpt'];
            $data['author'] = $list_decoded[$key]['author'];

            //if we want to send to the user "author" linked t the file
            //$user_author_data = get_userdata( $data['author'] );
            if(!isset($data['post_content']) || $data['post_content'] == ''){
                $data['post_content'] = " ";
            }

            /* INSERT POST */
            $new_post_id = $this->insert_post($data);
            if($new_post_id){

                //set language if there is one;
                if($language_slug !== 0){
                    pll_set_post_language($new_post_id, $language_slug);

                    //array to save later to associate posts between them
                    $list_assoc_language[$language_slug] = $new_post_id;
                }

                $category_slug = $line[$association_list['post_category']];
                if($category_slug && $category_slug != ""){
                    $the_cat_id = get_category_by_slug( $category_slug );
                    if($the_cat_id && $the_cat_id!=""){
                        wp_set_post_categories( $new_post_id, $the_cat_id->term_id);
                    }
                }            
                /* add the postmeta of unique csv id */
                update_post_meta( $new_post_id, $this->_wacmetakey, $unique_id_value);

                //TAXOS                
                foreach($association_list['taxonomie'] as $key_taxo=>$taxo_value){
                    $list_terms = array();

                    if($line[$taxo_value] && $line[$taxo_value]!=""){
                        $term_data = get_term_by('name',$line[$taxo_value],$key_taxo);
                        if(isset($term_data->term_id)){
                            $list_terms[] = $term_data->term_id;
                        }else{
                            //$list_terms[] = $line[$taxo_value];
                            $message[$new_post_id][] = "---TAXO : ".$key_taxo." - Terme ".$line[$taxo_value]." n'as pas été trouvé<br>";
                        }
                    }

                    if(isset($association_list['subtaxonomie'][$key_taxo])){

                        $subtaxo_value = $association_list['subtaxonomie'][$key_taxo];
                        if($subtaxo_value && $subtaxo_value!="" && $line[$subtaxo_value]!=""){
                            $subterm_data = get_term_by('name',$line[$subtaxo_value],$key_taxo);
                            if($subterm_data->term_id){
                                $list_terms[] = $subterm_data->term_id;
                            }else{
                                //$list_terms[] = $line[$subtaxo_value];
                                //
                                $message[$new_post_id][] = "---TAXO : ".$key_taxo." - Terme ".$line[$subtaxo_value]." n'as pas été trouvé<br>";
                            }
                        }

                    }    

                    //wp_set_object_terms( $new_post_id,$list_terms, $key_taxo,true); //create is doesn't exit NEED TO UNCOMENT THE LINES BEFORE
                    $returnTerm = wp_set_post_terms( $new_post_id,$list_terms , $key_taxo,true); ///do not create if does not exit
                }

                //update acfs
                foreach($list_acf as $acf_key=>$acf_value){
                    update_field($acf_key, $acf_value, $new_post_id);
                }
            }//end if new post

        }//end foreach language

        //after loop associate all posts between them
        pll_save_post_translations($list_assoc_language);

        return $message;
    }

    public function insert_post($data){
        $new_post = array();
        $new_post['post_title'] = $data['post_title'];
        $new_post['post_author'] = $data['author'];
        $new_post['post_status'] = $data['post_status'];
        $new_post['post_type'] = $data['post_type'];
        $new_post['post_content'] = $data['post_content'];

        $post_id = wp_insert_post( $new_post, true );
        $error_html = '';
        if (is_wp_error($post_id)) {
            $errors = $post_id->get_error_messages();
            foreach ($errors as $error) {
                //$error_html.=$error;
                //echo $error;
                //todo renvoyer un message d'erreur
            }
            return false; //$error_html;
        }
        return $post_id;
    }

    public function wac_editcsvdocument(){
        $return = false;
        $list_urls = get_option($this->_list_save_name,false); 
        if($list_urls){
            $list_decoded = json_decode($list_urls,true);
            $thedata = $list_decoded[$_POST['wacdoc']];
            $return = $this->selectorfields($thedata['firstline'], $thedata['cpt'],$_POST['wacdoc'],$thedata['association']);            
        }

        echo $return;
        wp_die();
    }

    public function wac_deletecsvdocument(){
        $return = false;
        $list_urls = get_option($this->_list_save_name,false); 
        if($list_urls){
            $list_decoded = json_decode($list_urls,true);
            unset($list_decoded[$_POST['wacdoc']]);
            $tosave = json_encode($list_decoded);
            update_option($this->_list_save_name,$tosave);
            $return = $_POST['wacdoc'];
        }
        echo json_encode($return);
        wp_die();
    }

    public function wacimportcsvoptions_main_menu_options() {

        echo '<div class="wrap">';
        echo '<h2>'.__('Import depuis fichier CSV','yd_import_csv').'</h2>';

        /** LIST URLS **/
        echo '<hr>';
        echo '<div id="messagepostprocess">'.$this->_message_post_process.'</div>';
        echo '<h2>Modèles d\'importation</h2>';
        $list_urls = get_option($this->_list_save_name,false);        
        $list_decoded = array();
        if($list_urls){
            $list_decoded = json_decode($list_urls,true);
            echo '<table class="modeles_liste">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Nom</th>';
            echo '<th>Type de contenu</th>';
            echo '<th>Action</th>';
            echo '</tr>';
            echo '</thead>';
            $count_line_save = 0;
            foreach($list_decoded as $key_ls=>$ls){
                echo '<tr id="wac_'.$key_ls.'">';
                echo '<td>';
                echo $key_ls;
                echo '</td>';

                echo '<td>';
                echo '</td>';

                echo '<td>';

                echo '<input type="button" value="Delete" id="wac_delete_save" data-li="'.$key_ls.'" style="width:150px;height:30px;">';
                echo '<input type="button" value="Edit" id="wac_edit_save" data-li="'.$key_ls.'" style="width:150px;height:30px;">';
                echo '<input type="button" value="Séléctionner fichier" id="wac_processfile'.$count_line_save.'" data-input="'.$count_line_save.'" data-li="'.$key_ls.'" style="width:150px;height:30px;">';

                //form
                echo '<form action="" method="POST" enctype="multipart/form-data">';
                echo '<input type="hidden" name="wacfilecsv_namesave" value="'.$key_ls.'">';
                echo '<input type="file" id="wac_processfile_input'.$count_line_save.'" name="wacfilecsvprocess" style="display:none;">';
                echo '<input type="submit" id="wac_processfile_button'.$count_line_save.'" value="Process fichier" style="display:none;">';
                echo '</form>';

                echo '</td>';
                echo '</tr>';
                $count_line_save++;
            }
            echo '</table>';
        }

        echo '<div id="html_admin_assoc_cpt">';
        echo $this->_html_admin;
        echo '</div>';

        echo '<hr>';
        echo '<h2>Nouveau modèle d\'importation</h2>';
        /** form to ad url **/
        echo '<form action="" method="POST" enctype="multipart/form-data">';

        /** NAME **/
        echo '<div>';
        echo 'Nom du modèle<br>';
        echo '<input type="text" name="namesauvegarde" >';
        echo '</div>';

        /** STARTING LINE **/
        echo '<div>';
        echo 'N° de la ligne contenant le nom des colonnes<br>';
        echo '<input type="text" name="startline" value="1" >';
        echo '</div>';

        /** TYPE ACTION POUR ABSENT **/
        echo '<div>';
        echo 'Si un contenu n\'est plus présent dans le fichier importé lors d\'une mise à jour:<br>';
        echo '<select name="actionligneabsente">';
        echo '<option value="ignorerpost">Conserver le contenu existant</option>';
        echo '<option value="deletepost">Supprimer le contenu existant</option>';
        echo '</select>';
        echo '</div>';

        /** TYPE separateur **/
        echo '<div>';
        echo 'Séparateur de champ<br>';
        echo '<input type="text" name="separatortype" value="," >';
        echo '</div>';

        /** CPT **/
        //Get all the cpt
        $args_cpt = array('public'   => true);
        $list_cpt = get_post_types($args_cpt);
        echo '<div>';
        echo '<div>'.__('Contenu à importer : ',',importcsv').'</div>';
        echo '<select name="cptsave" style="width:150px;">';
        echo '<option value="">'.__('Contenu à importer ',',importcsv').'</option>';
        foreach($list_cpt as $cpt_code=>$cpt_name){
            $selected = '';
            if($cpt_code == $cptlinked){
                $selected = 'selected';
            }
            echo '<option value="'.$cpt_code.'" '.$selected.'>'.$cpt_name.'</option>';
        }
        echo '</select>';
        echo '</div>';

        /** SELECT A AUTHOR **/
        $args_users = array(
            'role__in'     => array('administrator','editor','author')
        );
        $all_users = get_users( $args_users );
        echo '<div>';
        echo '<div>'.__('Auteur par défaut : ',',importcsv').'</div>';
        echo '<select name="authorsave" style="width:150px;">';
        echo '<option value="">'.__('Auteur par défaut ',',importcsv').'</option>';
        foreach($all_users as $user){
            $selected = '';
            if($user->ID == $author_selected){
                $selected = 'selected';
            }
            echo '<option value="'.$user->ID.'" '.$selected.'>'.$user->data->display_name.'</option>';
        }
        echo '</select>';
        echo '</div>';

        echo "<div>";
        echo '<span><input type="file" name="wacfilecsv"></span>';
        echo '<input type="submit" value="Créer un modèle à partir de ce fichier">';
        echo '</div>';
        echo '</form>';

        echo '</div>';
    }

    public function create_select_form($fields,$select_name,$default_value = null){
        $html = '';
        if(isset($fields) && $fields != ""){
            $html.= '<select name="'.$select_name.'">';

            if($default_value === 'notselected'){
                $select = "selected";
            }
            $html.= '<option value="notselected" '.$select.'>Select a field</option>';

            foreach( $fields as $field_value => $field_name ){

                $select = "";
                if((string)$default_value === (string)$field_value){
                    $select = "selected";
                }
                $html.= '<option value="'.$field_value.'" '.$select.'>';
                $html.= $field_name;
                $html.= '</option>';
            }

            $html.='</select>';
        }
        return $html;
    }    
}
