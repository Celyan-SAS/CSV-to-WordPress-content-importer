<?php
class wacimportcsv{

    public $_list_save_name = "wac_list_save_name_importcsv";
    private $_html_admin;
    private $_message_post_process;
    private $_wacmetakey = "yd_csv_import_line_id";
	private $_list_assoc_language;

    public function __construct() {

        /**ADMIN**/
        if(!is_admin()){
            return;
        }
        
        /** Délai d'exécution illimité **/
        ini_set('max_execution_time', 0);
        set_time_limit(0);
        
        add_action('admin_menu', array($this, 'importcsv_menu'));

        //add_action ('init',array($this,'process_post'));
        add_action ('admin_init',array($this,'process_post'));

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
        wp_register_style('style_csv', plugins_url('/css/style_csv.css', dirname(__FILE__)), array(), '1.0.1', 'all');
        wp_enqueue_style('style_csv'); // Enqueue it!
    }

    public function read_csv($file,$startingline,$separatortype=",",$limitline = 0){

        $return_array = array();
        $row_count = 0;
        if (($handle = fopen($file, "r")) !== FALSE) {
//var_dump( $file );
            while (($data = fgetcsv($handle, 0, $separatortype)) !== FALSE) {
//var_dump( $data );
//                $data = array_map("utf8_encode", $data); //added
//var_dump( $data );
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
        } else {
		wp_die( 'fopen missed' );
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
            $startline = intval($_POST['startline'])-1;
            if($startline<0){
               $startline = 0;
            }
            $data_first_line = $this->read_csv($_FILES["wacfilecsv"]["tmp_name"],$startline,$_POST['separatortype'],0);   
            $first_line = array();
            foreach($data_first_line as $dfl){
                $first_line = $dfl;
                break;
            }
            
            /* STARTING LINE */
            $list_decoded[$_POST['namesauvegarde']]['startline'] = $_POST['startline'];
            /* SAVE AUTHOR */
            $list_decoded[$_POST['namesauvegarde']]['author'] = $_POST['authorsave'];
            /* SAVE CPT */
            $list_decoded[$_POST['namesauvegarde']]['cpt'] = $_POST['cptsave'];
            /* save first line */
            $list_decoded[$_POST['namesauvegarde']]['firstline'] = $first_line;
            /* save first line */
            $list_decoded[$_POST['namesauvegarde']]['separatortype'] = $_POST['separatortype'];
            /* save first line */
            $list_decoded[$_POST['namesauvegarde']]['actionligneabsente'] = $_POST['actionligneabsente'];
            /** Save ignore languages **/
            $list_decoded[$_POST['namesauvegarde']]['ignorelang'] = $_POST['ignorelang'];

            /*reencode */
            $tosave = json_encode($list_decoded);
            /*save*/
            update_option($this->_list_save_name,$tosave);

            //NEXT PART         
            if(!empty($first_line)){
                $this->selectorfields($first_line,$_POST['cptsave'],$_POST['namesauvegarde'],null,$_POST['ignorelang']);
            }else{
                //TODO return error
                echo "<pre>", print_r("WHY?", 1), "</pre>";
                echo "<pre>", print_r($data_first_line, 1), "</pre>";
                die("STOP");
            }
        }

        if(isset($_POST['associatecptcolumn']) && $_POST['associatecptcolumn']!=""){

            $alldata = $_POST;
            $name_index = $_POST['namesauvegarde'];
            unset($alldata['associatecptcolumn']);
            unset($alldata['namesauvegarde']);

            $to_save_data = array();
            foreach($alldata as $idcol=>$data){
                $id_data = explode('|',$idcol);
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
    
    /**
     * needs to return an array of languages slug/names
     */
    public function get_languages_cols( $ignorelang=false ){
        $list = array();
        
        //test if we have pll
        if( !$ignorelang && is_plugin_active('polylang/polylang.php')){
            $pll_name = pll_languages_list(array('fields'=>'name'));
            $pll_slug = pll_languages_list(array('fields'=>'slug'));
            foreach($pll_name as $key=>$val){
                $list[$pll_slug[$key]] = $val;
            }
        }
        
        //todo can put another kind of language handler
        
        //else return defaults
        if(count($list)<=0){
            $list["dflt"] = "Defaut";
        }        
        
        return $list;
    }
    
    /*
     * Donne l'html tableau de modification des l'association col/champ
     */
    public function selectorfields($titles,$cptlinked,$namesauvegarde,$association_list = array(),$ignorelang=false,$thedatacomplete=false){

        $html = '';
        
        $html .= '<pre>Ignorelang:' . print_r( $ignorelang, true ) . '</pre>';	//Debug

        $cols_list = $this->get_languages_cols( $ignorelang );
        
        $html.= '<hr>';
        $html.= '<h2>Modifier le modèle d\'importation</h2>';
        $html.= '<div class="modif_modele_name"><strong>'.$namesauvegarde.'</strong></div>';
        $html.= '<form action="" method="POST" enctype="multipart/form-data">';
        $html.= '<div>';

//var_dump( $titles );

		$list_groups = acf_get_field_groups();
		$fields_acf = false;
		foreach($list_groups as $LG){
			if($LG['location'][0][0]['value'] == $cptlinked){
				$fields_acf = acf_get_fields($LG['key']);
			}
		}
		
//        if(isset($cptlinked) && $cptlinked != ""){
//            $args = array(
//                'posts_per_page'   => 1,
//                'post_type'        => $cptlinked,
//                'post_status'      => "any",
//                'order'   => 'ASC',
//                'orderby' =>  'ID'
//            );
//            $cptposts = get_posts( $args );            
//            if(isset($cptposts[0]->ID) && $cptposts[0]->ID != ""){
//                //will be used later to creafte select fields
//                $fields_acf = get_field_objects($cptposts[0]->ID);
//            }
//        }

        $html.= '<form action="" method="POST">';
        $html.= '<table class="wp-list-table widefat fixed striped modele_modifier">';

        $html.= '<thead>';
        $html.= '<tr>';
        $html.= '<th class="column-primary">';
        $html.= 'Méta-données';
        $html.= '</th>';

        foreach($cols_list as $keyppl=>$ppl_name){
            $html.= '<th>';
            $html.= $ppl_name;
            $html.= '</th>';
        }

        $html.= '</tr>';
        $html.= '</thead>';
        $html.= '<tbody>';

        //////////////////
        //Add basic fields
        //////////////////
        $array_list_fields_wp = array(
            'id_unique'=>'Colonne identifiante',
            'post_title'=>'Post titre',
            'post_content'=>'Post content',
            'post_category'=>'Post catégorie',
			'post_date'=>'Post date', 
			'post_date_gmt'=>'Post date gmt'
        );	
		
        foreach($array_list_fields_wp as $fieldkey=>$fieldname){
            //LINE -------------------------
            $html.= '<tr>';

            //TITLE COL --------------------------
            $html.= '<td>';
            $html.= '<strong style="width: 250px;display: inline-block;">';
            $html.= $fieldname;
            if( $fieldkey == 'id_unique' ){
                $html.= '<br/><small>Laisser vide pour forcer la création du post.</small>';
            }
            $html.= '</strong>';
            $html.= '</td>';

            //COL BY LANGUAGE --------------------
            foreach($cols_list as $keyppl=>$ppl_name){
                $html.= '<td data-colname="'.$ppl_name.'">';
                $default_value = "notselected";
                if(isset($association_list[$keyppl]) && $association_list[$keyppl][$fieldkey] !== null){
                    $default_value = $association_list[$keyppl][$fieldkey];
                }
                $html.= $this->create_select_form($titles, $fieldkey.'|'.$keyppl,$default_value);

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

            //LINE -------------------------
            $html.= '<tr>';

            //TITLE COL --------------------------
            $html.= '<td>';
            $html.= '<strong style="width: 250px;display: inline-block;">';
            $html.= 'TAXO : '.$taxo_assoc;
            $html.= '</strong>';
            $html.= '</td>';

            //COL BY LANGUAGE --------------------
            foreach($cols_list as $keyppl=>$ppl_name){
                $html.= '<td data-colname="'.$ppl_name.'">';
                $default_value = "notselected";
                if(isset($association_list[$keyppl]) && $association_list[$keyppl]['taxonomie'][$taxo_assoc] !== null){
                    $default_value = $association_list[$keyppl]['taxonomie'][$taxo_assoc];
                }
                $html.= $this->create_select_form($titles, "taxo_".$taxo_assoc.'|'.$keyppl,$default_value);
                $html.= '</td>';
            }
            $html.= '</tr>'; 


            if(isset($data_taxonomy->hierarchical) && $data_taxonomy->hierarchical==1){

                $html.= '<tr>';
                //TITLE COL --------------------------
                $html.= '<td>';
                $html.= '<strong style="width: 250px;display: inline-block;">';
                $html.= 'SOUS TAXO : '.$taxo_assoc;
                $html.= '</strong>';
                $html.= '</td>';

                foreach($cols_list as $keyppl=>$ppl_name){
                    //create sous rubrique
                    $html.= '<td data-colname="'.$ppl_name.'">';
                    $default_value = "notselected";
                    if(isset($association_list[$keyppl]) && $association_list[$keyppl]['subtaxonomie'][$taxo_assoc] !== null){
                        $default_value = $association_list[$keyppl]['subtaxonomie'][$taxo_assoc];
                    }
                    $html.= $this->create_select_form($titles, "subt_".$taxo_assoc.'|'.$keyppl,$default_value);
                    $html.= '</td>';  
                }

                $html.= '</tr>'; 
            }
        }

        /////////////////
        //Add Post status
        /////////////////

        //LINE -------------------------
        $html.= '<tr>';

        //COL TITLE ----------------
        $html.= '<td>';
        $html.= '<strong style="width: 250px;display: inline-block;">';
        $html.= "Post status";
        $html.= '</strong>';
        $html.= '</td>';

        //COL ----------------------
        foreach($cols_list as $keyppl=>$ppl_name){
            $html.= '<td data-colname="'.$ppl_name.'">';
            $list_status = array(
                'publish'=>'Publié',
                'draft'=>'Brouillon'
            );
            $default_value = "notselected";
            if(isset($association_list[$keyppl]) && $association_list[$keyppl]['post_status'] !== null){
                $default_value = $association_list[$keyppl]['post_status'];
            }
            $html.= $this->create_select_form($list_status, 'post_status|'.$keyppl,$default_value);
            $html.= '</td>';
        }

        $html.= '</tr>';

        ////////////////
        //Add acf fields
        ////////////////
		if($fields_acf && count($fields_acf)>0){		
			foreach( $fields_acf as $field_slug => $field_data ){
				//LINE--------------------------
				$html.= '<tr>';

				//COL ----------------------
				$html.= '<td>';
				$html.= '<strong style="width: 250px;display: inline-block;">';
				$html.= $field_data['label'];
				$html.= '</strong>';
				$html.= '<div><span>'.$field_data['name'].'</span></div>';
				$html.= '</td>';

				//COL-----------------------
				foreach($cols_list as $keyppl=>$ppl_name){
					$html.= '<td data-colname="'.$ppl_name.'">';
					$default_value = "notselected";
					if(
						!empty($association_list[$keyppl][$field_data['key']]) 
						&& $association_list[$keyppl][$field_data['key']] !== null
					){
						$default_value = $association_list[$keyppl][$field_data['key']];
					}

					$html.= $this->create_select_form($titles, $field_data['key'].'|'.$keyppl,$default_value);

					$default_value = "";
					//                        if($association_list[$field_data['key'].'_text'] !== null){
					//                           $default_value = $association_list[$field_data['key'].'_text'];
					//                        }

					//$html.= '<input type="text" name="'.$field_data['key'].'_text" value="'.$default_value.'">';
					$html.= '</td>';
				}

				$html.= '</tr>';
			}
		}

        $html.= '</tbody>';
        $html.= '</table>';
        $html.= '</div>';
        $html.= '<input value="'.$namesauvegarde.'" name="namesauvegarde" type="hidden">';
        $html.= '<input value="1" name="associatecptcolumn" type="hidden">';
		
		if(!empty($thedatacomplete['separatortype'])){
			$html.= "<div style='color:red;'>";
			$html.= "Attention le séparateur est :     ".$thedatacomplete['separatortype'];
			$html.= "</div>";
		}
		
        $html.= '<input class="button button-primary maj_modele" type="submit" value="Mettre à jour le modèle">';
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
        $icon_url = 'dashicons-upload';

        add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function, $icon_url);
    }

    public function import_data_from_csv($file,$key){

        //get data from key
        $list_urls = get_option($this->_list_save_name,false);
        $list_decoded = json_decode($list_urls,true);
        $data_save = $list_decoded[$key];

        $startline = $data_save['startline'];
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
        $id_postmeta = "notselected";
		
//echo "<pre>", print_r("UNIQUE ID", 1), "</pre>";
//echo "<pre>", print_r($association_list, 1), "</pre>";
//		
//        if(isset($association_list['id_unique'])){
//            $id_postmeta = $association_list['id_unique'];
//        }

        $ajout_post = 0;
		$update_post = 0;
        $delete_post = 0;
        //parse csvlines if no $this->_wacmetakey exist, create post
        $message_lines = array();
		$this->_list_assoc_language = array();
        foreach($values as $line){
            			
			$association_list_language = $list_decoded[$key]['association'];
			foreach($association_list_language as $language_slug=>$association_list){
				
				//ID dans le tableau (POSITION COLONNE, pas l'id du post)
				$id_postmeta = $association_list['id_unique'];
				$uniqueval_time = time();
				$the_WP_post_id = false;
				if(isset($line[$id_postmeta])){
					$uniqueval_time = $line[$id_postmeta];
					$the_WP_post_id = $line[$id_postmeta];
				}
                $unique_id_value = md5($language_slug.'_'.$uniqueval_time);
						
				//Update line
				$post_id_update = false;
				if(isset($postmeta_list[$unique_id_value])){
					$post_id_update = $postmeta_list[$unique_id_value];
				}
									
				$message_lines = $this->create_post(
													$line,
													$list_decoded,
													$key,
													$unique_id_value,
													$language_slug,
													$association_list,
													$the_WP_post_id);
				
				unset($postmeta_list[$unique_id_value]); //virer du tableau ceux qui on été trouvé pour finir avec ceux qui n'ont pas été ajoutés
//                $update_post++;
//            }else{                
//                unset($postmeta_list[$unique_id_value]); //virer du tableau ceux qui on été trouvé pour finir avec ceux qui n'ont pas été ajoutés
//                $message_lines = $this->create_post($line,$list_decoded,$key,$unique_id_value);
                $ajout_post++;
//}
			}//end foreach language
			
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
		//$message.= $update_post.' ont été mis à jour.<br>';
        $message.= $delete_post.' ont été supprimés.<br>';
        foreach($message_lines as $idpostml=>$ml){
            $url = get_edit_post_link($idpostml);
            $message.= "Pour le post : ".get_the_title($idpostml)." <a href='".$url."'>$url</a><br>";
            $message.= implode('',$ml);
        }        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $t = wp_mail($to,$subject,$message,$headers);
    }
	
    public function create_post($line,$list_decoded,$key,$unique_id_value,$language_slug,$association_list,$post_id_update=false){   

        $message = array();

/* DEBUG *
echo "POST ID UPDATE dans create_post:<br/>\n";
var_dump( $post_id_update );
exit;
/* */

        //loop for language
        //$list_assoc_language = array();
//        foreach($association_list_language as $language_slug=>$association_list){
            
            $data = array();
            //create a post
            $list_acf = array();
            
            foreach($association_list as $key_al => $value_al){
                if(isset($value_al) && ($value_al == "notselected" || $value_al=="" ) ){
                    continue;
                }
                //will be treated afdter
                if(isset($key_al) && ($key_al=="subtaxonomie" || $key_al=="taxonomie")){
                    continue;
                }
                
                //$association_list[$key_al] gives the key to seek
                if(isset($line[$value_al])){
                    $data[$key_al] = $line[$value_al];
					
					if(preg_match('#field_#', $key_al)){
						$key_al = str_replace('_text', '', $key_al);
						$list_acf[$key_al] = $line[$value_al];
					}					
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
						
			/** date if used **/
			if(isset($association_list['post_date_gmt']) 
				&& isset($line[$association_list['post_date_gmt']])
			){
				$data['post_date_gmt'] = $line[$association_list['post_date_gmt']];
			}
			if(isset($association_list['post_date']) 
				&& isset($line[$association_list['post_date']])
			){
				$data['post_date'] = $line[$association_list['post_date']];
			}
			
            /* INSERT POST */   
			$new_post_id = $this->insert_post($data,$post_id_update);	// PROVISOIREMENT COMMENTE YD 14/02/2022
			//$new_post_id = $post_id_update;				// PROVISOIRE YD 14/02/2022
			
            do_action( 'wpc_importcsv_newpost', $new_post_id, $list_acf );
            
            if($new_post_id){

                //set language if there is one;
                if($language_slug !== 0){
                    if(is_plugin_active('polylang/polylang.php')){
                        pll_set_post_language($new_post_id, $language_slug);
                        //array to save later to associate posts between them
                        $this->_list_assoc_language[$language_slug] = $new_post_id;
                    }
                }

                if(isset($association_list['post_category']) && isset($line[$association_list['post_category']])){
                    $category_slug = $line[$association_list['post_category']];
                    if($category_slug && $category_slug != ""){
                        $the_cat_id = get_category_by_slug( $category_slug );
                        if($the_cat_id && $the_cat_id!=""){
                            wp_set_post_categories( $new_post_id, $the_cat_id->term_id);
                        }
                    }            
                }
                /* add the postmeta of unique csv id */
                update_post_meta( $new_post_id, $this->_wacmetakey, $unique_id_value);

                //TAXOS
                if( !empty( $association_list['taxonomie'] ) && is_array( $association_list['taxonomie'] ) ) {
	                foreach($association_list['taxonomie'] as $key_taxo=>$taxo_value){
	                    $list_terms = array();
	
	                    if($taxo_value == "notselected"){
	                        continue;
	                    }
	                    
	                    
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
	                        if($subtaxo_value && $subtaxo_value!="" && $subtaxo_value!='notselected' &&$line[$subtaxo_value]!=""){
								
								$subtermid = false;
								$subterm_data = get_terms( array(
									'taxonomy'		=> 'type_boutiques',
									'name'			=> $line[$subtaxo_value],
									'hierarchical'	=> true,
									'hide_empty'	=> false
								) );					
								foreach($subterm_data as $subdata){
									if(isset($subdata->parent) && $subdata->parent!=0 && $subdata->parent!="0"){
										//this is it
										$subtermid = $subdata->term_id;
									}
								}
								if($subtermid){
									$list_terms[] = $subtermid;
								}else{
									$message[$new_post_id][] = "---TAXO : ".$key_taxo." - Terme ".$line[$subtaxo_value]." n'as pas été trouvé<br>";
								}
								
	//                            $subterm_data = get_term_by('name',$line[$subtaxo_value],$key_taxo);
	//                            if(isset($subterm_data->term_id) && $subterm_data->term_id){
	//                                $list_terms[] = $subterm_data->term_id;
	//                            }else{
	//                                $message[$new_post_id][] = "---TAXO : ".$key_taxo." - Terme ".$line[$subtaxo_value]." n'as pas été trouvé<br>";
	                            //}
	                        }
	
	                    }    
	
	                    //wp_set_object_terms( $new_post_id,$list_terms, $key_taxo,true); //create is doesn't exit NEED TO UNCOMENT THE LINES BEFORE
	                    $returnTerm = wp_set_post_terms( $new_post_id,$list_terms , $key_taxo,false); ///do not create if does not exit
	                }
                }
                
                //update acfs
                foreach($list_acf as $acf_key=>$acf_value){					
					$acf_value = apply_filters('csvtowp_updatefield_value',$acf_value,$acf_key,$new_post_id);
					
					if($acf_value != "csvtowp_cancel_update"){
						update_field($acf_key, $acf_value, $new_post_id);
					}
                }
				
				update_field('import_by_tool', date("Y-m-d H:i:s"), $new_post_id);
				
            }//end if new post

        //}//end foreach language

        if(is_plugin_active('polylang/polylang.php')){
            //after loop associate all posts between them
            pll_save_post_translations($this->_list_assoc_language);
        }

        return $message;
    }

    public function insert_post($data,$post_id_update=false){
        $new_post = array();
		if(!empty($data['post_title'])){
			$new_post['post_title'] = $data['post_title'];
		}
		if(!empty($data['post_author'])){
			$new_post['post_author'] = $data['post_author'];
		}
		if(!empty($data['post_status']) && $data['post_status']!="notselected"){
			$new_post['post_status'] = $data['post_status'];
		}
		if(!empty($data['post_type'])){
			$new_post['post_type'] = $data['post_type'];
		}
		if(!empty($data['post_content']) && trim($data['post_content'])!=""){
			$new_post['post_content'] = $data['post_content'];
		}		
		if(!empty($data['post_date_gmt'])){
			$new_post['post_date_gmt'] = $data['post_date_gmt'];
		}
		if(!empty($data['post_date'])){
			$new_post['post_date'] = $data['post_date'];
		}
				
		if(!$post_id_update){			
			$post_id = wp_insert_post( $new_post, true );	
			
		}else{		
			$new_post['ID'] = $post_id_update;
			$post_id = $post_id_update;
			$upa = wp_update_post( $new_post, true );		
			
		}
        $error_html = '';
        if (is_wp_error($post_id)) {
            $errors = $post_id->get_error_messages();
            foreach ($errors as $error) {
                $error_html.=$error;
                echo $error;
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
            $return = $this->selectorfields($thedata['firstline'], $thedata['cpt'],$_POST['wacdoc'],$thedata['association'],$thedata['ignorelang'],$thedata);            
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
        
        if(isset($_GET['details'])){
            $titreliendetails = "Masquer les détails";
            $detailsurl = remove_query_arg( 'details');
        }else{
            $titreliendetails = "Détails";
            $detailsurl = add_query_arg( 'details', '1');
        }
		
        echo '<h2 style="display:inline-block;">Modèles d\'importation</h2>&nbsp;<a href="'.$detailsurl.'">('.$titreliendetails.')</a>';
        
        $list_urls = get_option($this->_list_save_name,false);
        
        $list_decoded = array();
				
        if(
			!empty($list_urls)
			&& $list_urls != "[]"
		){
            $list_decoded = json_decode($list_urls,true);
            echo '<table class="modeles_liste wp-list-table widefat fixed striped posts">';
            echo '<thead>';
            echo '<tr class="manage-column column-title column-primary">';

            if(isset($_GET['details'])){
                echo '<th class="column-primary">Nom</th>';
            }

            echo '<th>Type de contenu</th>';
            echo '<th>Action</th>';
            echo '</tr>';
            echo '</thead>';
            $count_line_save = 0;
            foreach($list_decoded as $key_ls=>$ls){
                echo '<tr class="modele" id="wac_'.$key_ls.'">';
				
                if(isset($_GET['details'])){
                    echo '<td class="modele_name has-row-actions column-primary">';
                    echo '<strong class="modele_title row-title" onClick="jQuery(\'#wac_edit_save\').click()">'.$key_ls.'</strong>';
                    echo '<div class="row-actions">';
                    echo '<span class="edit"><input type="button" value="Modifier" id="wac_edit_save" data-li="'.$key_ls.'"> | </span>';
                    echo '<span class="trash"><input type="button" value="Supprimer" id="wac_delete_save" data-li="'.$key_ls.'"></span>';
                    echo '</div>';
                    echo '<button type="button" class="toggle-row"><span class="screen-reader-text">Afficher plus de détails</span></button>';
                    echo '</td>';
                }else{
					echo '<td class="modele_name has-row-actions column-primary">';
					echo '<strong class="modele_title row-title">'.$key_ls.'</strong>';
					echo '</td>';
				}

                echo '<td class="slug column-slug column-cpt" data-colname="Type de contenu">';
                echo '<span>'.$ls['cpt'].'</span>';
                echo '</td>';
                echo '<td class="column-action" data-colname="Action">';
                echo '<input class="button" type="button" value="Fichier..." id="wac_processfile'.$count_line_save.'" data-input="'.$count_line_save.'" data-li="'.$key_ls.'" style="width:150px;height:30px;line-height: 15px;">';

                //form
                echo '<form action="" method="POST" enctype="multipart/form-data" style="display: inline-block;">';
                    echo '<input type="file" id="wac_processfile_input'.$count_line_save.'" name="wacfilecsvprocess" style="display:none;">';
                    echo '<div style="display: inline-block;"><input type="hidden" name="wacfilecsv_namesave" value="'.$key_ls.'"></div>';
                    echo '<div style="display: inline-block;"><input class="button-primary" type="submit" id="wac_processfile_button'.$count_line_save.'" value="Importer" style="display:none;"></div>';
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
        
        if(isset($_GET['details'])){
            echo '<hr>';
            echo '<h2>Nouveau modèle d\'importation</h2>';
            /** form to ad url **/
            echo '<form action="" method="POST" enctype="multipart/form-data">';

            echo '<table class="add_modele">';
            /** NAME **/
            echo '<tr>';
            echo '<th>';
            echo 'Nom du modèle';
            echo '</th>';
            echo '<td>';
            echo '<input type="text" name="namesauvegarde" >';
            echo '</td>';
            echo '</tr>';

            /** STARTING LINE **/
            echo '<tr>';
            echo '<th>';
            echo 'N° de la ligne contenant le nom des colonnes';
            echo '</th>';
            echo '<td>';
            echo '<input type="text" name="startline" value="1" >';
            echo '</td>';
            echo '</tr>';

            /** TYPE ACTION POUR ABSENT **/
            echo '<tr>';
            echo '<th>';
            echo 'Si un contenu n\'est plus présent dans le fichier importé lors d\'une mise à jour:';
            echo '</th>';
            echo '<td>';
            echo '<select name="actionligneabsente">';
            echo '<option value="ignorerpost">Conserver le contenu existant</option>';
            echo '<option value="deletepost">Envoyer le contenu dans la corbeille</option>';
            echo '</select>';
            echo '</td>';
            echo '</tr>';

            /** TYPE separateur **/
            echo '<tr>';
            echo '<th>';
            echo 'Séparateur de champ';
            echo '</th>';
            echo '<td>';
            //echo '<input type="text" name="separatortype" value="," >';
            echo '<select name="separatortype">';
            echo '<option value=",">, (virgule)</option>';
            echo '<option value=";">; (point virgule)</option>';
            echo '</select>';
            echo '</td>';
            echo '</tr>';

            /** CPT **/
            //Get all the cpt
            $args_cpt = array('public'   => true);
            $list_cpt = get_post_types($args_cpt);
            echo '<tr>';
            echo '<th>';
            echo '<div>'.__('Contenu à importer : ',',importcsv').'</div>';
            echo '</th>';
            echo '<td>';
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
            echo '</td>';
            echo '</tr>';

            /** SELECT A AUTHOR **/
            $args_users = array(
                'role__in'     => array('administrator','editor','author')
            );
            $all_users = get_users( $args_users );
            echo '<tr>';
            echo '<th>';
            echo '<div>'.__('Auteur par défaut : ',',importcsv').'</div>';
            echo '</th>';
            echo '<td>';
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
            echo '</td>';
            echo '</tr>';
            
            /** Option pour ne pas tenir compte des langues **/
            echo '<tr>';
            echo '<th>';
            echo '<div>' . __( 'Ignorer les langues', 'importcsv' ) . '</div>';
            echo '</th>';
            echo '<td>';
            echo '<label><input type="checkbox" name="ignorelang" id="ignorelang" value="1" /> Ignorer</label>';
            echo '</td>';
            echo '</tr>';

            echo '<tr>';
            echo '<th>';
            echo '<span><input type="file" name="wacfilecsv"></span>';
            echo '</th>';
            echo '<td>';
            echo '<input class="button button-primary" type="submit" value="Créer un modèle à partir de ce fichier">';
            echo '</td>';
            echo '</tr>';

            echo '</table>';
            echo '</form>';

            echo '</div>';
        }//end if details
    }

    public function create_select_form($fields,$select_name,$default_value = null){
        $html = '';
        if(isset($fields) && $fields != ""){
            $html.= '<select name="'.$select_name.'">';

            $select = "";
            if($default_value === 'notselected'){
                $select = "selected";
            }
            $html.= '<option value="notselected" '.$select.'>Sélectionner une colonne...</option>';

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
