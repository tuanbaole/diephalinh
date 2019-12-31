<?php
/*
Plugin Name: LadiPage - Landing Page Builder
Plugin URI: http://ladipage.com
Description: Connector to access content from LadiPage service. (LadiPage: Landing Page Platform for Advertiser)
Author: LadiPage
Author URI: http://ladipage.com
Version: 2.4
*/


if( ! class_exists( "PageTemplater" ) ){
	require plugin_dir_path( __FILE__ ) . 'add-template.php';
}

if ( isset( $_SERVER['HTTP_ORIGIN'] ) ) {
	header( "Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}" );
	header( 'Access-Control-Allow-Credentials: true' );
	header( 'Access-Control-Max-Age: 86400' ); // cache for 1 day
}

if ( $_SERVER['REQUEST_METHOD'] == 'OPTIONS' ) {
	if ( isset( $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ) ) {
		header( "Access-Control-Allow-Methods: GET, POST, OPTIONS" );
	}
	if ( isset( $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ) ) {
		header( "Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}" );
	}
	exit( 0 );
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Ladipage' ) ) {

	class Ladipage {
		protected static $_instance = null;

		protected $_notices = array();

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		public function __construct() {
			add_action( 'init', array( $this, 'init_endpoint' ) );
			add_action( 'admin_init', array( $this, 'check_environment' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
			add_action( 'admin_menu', array( $this, 'add_ladipage_menu_item' ) );
			add_action( 'wp_ajax_ladipage_save_config', array( $this, 'save_config' ) );
			add_action( 'wp_ajax_ladipage_publish_lp', array( $this, 'publish_lp' ) );

			register_activation_hook( __FILE__, array( $this, 'activation_process' ) );
			register_deactivation_hook( __FILE__, array( $this, 'deactivation_process' ) );
		}

		public function activation_process() {
		    $this->init_endpoint();
			flush_rewrite_rules();
		}

		public function deactivation_process() {
			flush_rewrite_rules();
		}

		/* add hook and do action */
		public function init_endpoint() {
			add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );
			add_action( 'parse_request', array( $this, 'sniff_requests' ) );
			add_rewrite_rule( '^ladipage/api', 'index.php?ladipage_api=1', 'top' );
		}

		public function add_query_vars( $vars ) {
			$vars[] = 'ladipage_api';

			return $vars;
		}

		public function sniff_requests() {
			global $wp, $wpdb;
			$query = $GLOBALS['wpdb'];
			$isLadipage = isset( $wp->query_vars['ladipage_api'] ) ? $wp->query_vars['ladipage_api'] : null;

			if ( ! is_null( $isLadipage ) && $isLadipage === "1" ) {
				$params      = filter_input_array( INPUT_POST );
				$api_key     = isset( $params['token'] ) ? sanitize_text_field($params['token']) : null;
				$action      = isset( $params['action'] ) ? sanitize_text_field($params['action']) : null;
				$url         = isset( $params['url'] ) ? sanitize_text_field($params['url']) : null;
				$title       = isset( $params['title'] ) ? sanitize_text_field($params['title']) : null;
				$html        = isset( $params['html'] ) ? $params['html'] : '';
				$type       = isset( $params['type'] ) ? sanitize_text_field($params['type']) : null;

				$config = get_option( 'ladipage_config', '');
					
				if ( $api_key !== $config['api_key'] ) {
					
					wp_send_json( array(
						'code'    => 190
					) );
				}
				switch ( $action ) {
					case 'create':
						if ( $this->get_id_by_slug($url) ) {
							wp_send_json( array(
								'code'    => 205
							) );
						}
						kses_remove_filters();
						if($type==null){
							$post_type = 'page';
						}else{
							$post_type = $type;
						}

						$query->insert( 
							$query->posts, 
							array(
								'post_title'=> $title, 
								'post_name'=> $url, 
								'post_type'=> 'page', 
								'post_content'=> trim($html), 
								'post_status' => 'publish'
							)
						);

						/*$id = wp_insert_post(
							array(
								'post_title'=>$title, 
								'post_name'=>$url, 
								'post_type'=>$post_type, 
								'post_content'=> trim($html), 
								'post_status' => 'publish',
								'filter' => true ,
								'page_template'  => 'null-template.php'
							)
						);*/

						if ($query && $query->insert_id) {
							$postID = $query->insert_id;
							if (empty(get_post_meta( $postID, '_wp_page_template', true )))
								add_post_meta($postID, '_wp_page_template', 'null-template.php');

							wp_send_json( array(
								'code'    => 200,
								'url'    => get_permalink($query->insert_id)
							) );
						}else{
							wp_send_json( array(
								'code'    => 400,
								'message' => __( 'Create failed, please try again.' )
							) );
						}
						break;
					
					case 'update':
						if ( ! $this->get_id_by_slug($url) ) {
							wp_send_json( array(
								'code'    => 400,
								'message' => __( 'URL does not exist' )
							) );
						}else{
							$id = $this->get_id_by_slug($url);
							/*$post = array(
					            'ID' => $id,
					            'post_title'=>$title, 
					            'post_content' => trim($html), 
							);
							$result = wp_update_post($post, true);*/
							$post = array(
								'post_title'=>$title, 
					            'post_content' => trim($html), 
							);

							$where = array(
								'ID' => $id
							);
					        kses_remove_filters();
							
							$query->update( $query->posts, $post, $where);
					        if ($query) {
								wp_send_json( array(
									'code'    => 200,
									'url'    => get_permalink($id)
								) );
					        } else {
					        	wp_send_json( array(
									'code'    => 400,
									'message' => __( 'Update failed, please try again.' )
								) );
					        }
						}
						break;

					case 'delete':
						if ( ! $this->get_id_by_slug($url) ) {
							wp_send_json( array(
								'code'    => 400,
								'message' => __( 'URL does not exist' )
							) );
						}else{
							$id = $this->get_id_by_slug($url);
							$result = wp_delete_post($id);
					        if($result){
					        	wp_send_json( array(
									'code'    => 200
								) );
					        }else{
					        	wp_send_json( array(
									'code'    => 400,
									'message' => __( 'Delete failed, please try again.' )
								) );
					        }
						}
						break;
					case 'checkurl':
						if ( ! $this->get_id_by_slug($url) ) {
							wp_send_json( array(
								'code'    => 206
							) );
						}else{
							wp_send_json( array(
								'code'    => 205
							) );
						}
						break;
					case 'checktoken':
						if ( $api_key === $config['api_key'] ) {
							wp_send_json( array(
								'code'    => 191
							) );
						}
						break;		
					default:
						wp_send_json( array(
							'code'    => 400,
							'message' => __( 'LadiPage action is not set or incorrect.' )
						) );
						break;
				}
			}
		}


		protected function get_id_by_slug($page_slug) {
		    $page = get_page_by_path($page_slug,'OBJECT', ['post','page','product','property']);
		    if ($page) {
		        return $page->ID;
		    } else {
		        return null;
		    }
		} 



		public function get_option( $id, $default = '' ) {
			$options = get_option( 'ladipage_config', array() );
			if ( isset( $options[ $id ] ) && $options[ $id ] != '' ) {
				return $options[ $id ];
			} else {
				return $default;
			}
		}

		/* Add menu and option */
		public function check_environment() {
			if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {
				if ( ! function_exists( 'curl_init' ) ) {
					$this->add_admin_notice( 'curl_not_exist', 'error', __( 'LadiPage requires cURL to be installed.', 'ladipage' ) );
				}
			}
		}

		public function add_admin_notice( $slug, $class, $message ) {
			$this->_notices[ $slug ] = array(
				'class'   => $class,
				'message' => $message,
			);
		}

		public function admin_notices() {
			foreach ( $this->_notices as $notice_key => $notice ) {
				echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
				echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
				echo '</p></div>';
			}
		}

		public function add_ladipage_menu_item() {
			add_menu_page( __( "LadiPage" ), __( "LadiPage" ), "manage_options", "ladipage-config", array(
				$this,
				'ladipage_settings_page'
			), null, 30 );
		}

		public function ladipage_settings_page() {
			if ( ! empty( $this->_notices ) ) {
				?>
                <div>Please install cURL to use LadiPage plugin</div>
				<?php
			} else {
				?>
                <div class="wrap">
                    <h2 class="title">LadiPage - Landing Page Builder</h2><br>
                    <form id="ladipage_config" class="ladiui-panel">
                    	<h3><strong>Config API Key</strong></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="api_key">API KEY</label>
                                </th>
                                <td>
                                	<?php
                                		$config = get_option( 'ladipage_config', array());
                                		
                            		    if(!isset($config['api_key']) || trim($config['api_key']) == ''){
                            		    	$config['api_key'] = $this->generateRandomString(32);
                            		    	update_option( 'ladipage_config', $config );
                            		    }

                                	?>
                                    <input onClick="this.select();" readonly="readonly" name="api_key" id="api_key" type="text" class="regular-text ladiui input"
                                           value="<?php echo $this->get_option( 'api_key', '' ); ?>">
                                           <button type="button" id="ladipage_new_api" class="ladiui button primary">NEW API KEY</button>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="webiste_ladipage">API URL</label>
                                </th>
                                <td>
                                    <input onClick="this.select();" readonly="readonly" name="webiste_ladipage" id="webiste_ladipage" type="text" class="regular-text ladiui input" value="<?php echo get_home_url(); ?>">
                                </td>
                            </tr>
                        </table>

                        <div class="submit">
                            <button class="button button-primary ladiui button primary" id="ladipage_save_option" type="button">Save Changes
                            </button>
                        </div>
                    </form>
                    <form class="ladiui-panel" id="ladipage-publish-form">
                    	<h3><strong>Manualy LadiPage Publish</strong></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="api_key">LadiPage KEY</label>
                                </th>
                                <td>
                                    <input name="ladipage_key" id="ladipage_key" type="text" class="regular-text ladiui input" placeholder="Your LadiPage Key"><br/>
                                </td>
                            </tr>
							<tr>
								<td></td>
								<td>
								<span id="ladipage-message" class="lp-hide" style="color:#0c61f2;font-style:italic">Processing...</span>
								<style>.lp-hide{display:none}</style>
								</td>
							</tr>
                        </table>
                        <div class="submit">
                            <button type="button" id="ladipage_publish" class="button button-primary ladiui button primary">Publish</button>
                        </div>
                    </form>
                    <script>
                        (function ($) {
                        	function generateRandomString(length = 10) {
							  	var text = "";
							  	var possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
							  	for (var i = 0; i < length; i++)
							    text += possible.charAt(Math.floor(Math.random() * possible.length));
							  	return text;
							}
                            $(document).ready(function () {
                                $('#ladipage_save_option').on('click', function (event) {
                                    var data = JSON.stringify($('#ladipage_config').serializeArray());
                                    $.ajax({
                                        url: ajaxurl,
                                        type: 'POST',
                                        data: {
                                            action: 'ladipage_save_config',
                                            data: data
                                        },
                                        success: function (response) {
                                            alert('Save Success');
                                        }
                                    });
                                    event.preventDefault();
                                });
                                $("#ladipage_new_api").click(function(){
                                	var api = generateRandomString(32);
                                	$("#ladipage_config #api_key").val(api);
                                });

                                $('#ladipage_publish').on('click', function (event) {
									event.preventDefault();
									$('#ladipage-message').removeClass('lp-hide');
									$('#ladipage-message').empty().text('Processing...');
                                	var ladiPageKey = $('#ladipage_key').val();
                                	if (ladiPageKey == '') {
                                		alert('Please enter your LadiPage Key!');
                                		return false;
                                	}

                                    $.ajax({
                                        url: ajaxurl,
                                        type: 'POST',
                                        data: {
                                            action: 'ladipage_publish_lp',
                                            ladipage_key: ladiPageKey
                                        },
                                        success: function (res) {
											$('#ladipage-message').empty().html(res.message);
                                        }
                                    });
                                    event.preventDefault();

                                });

                            });
                        })(jQuery);
                    </script>
                </div>
				<?php
			}
		}

		public function save_config() {
			$data   = sanitize_text_field($_POST['data']);
			$data   = json_decode( stripslashes( $data ) );
			$option = array();

			foreach ( $data as $key => $value ) {
				$option[ $value->name ] = $value->value;
			}
			update_option( 'ladipage_config', $option );
			die;
		}

		public function publish_lp() {
			global $wp, $wpdb;
			$query = $GLOBALS['wpdb'];
			if (isset($_POST['ladipage_key']) && trim($_POST['ladipage_key']) != '') {
				$ladiPageKey = trim($_POST['ladipage_key']);
				$url = sprintf("https://api.ladipage.com/2.0/get-source-by-ladipage-key?ladipage_key=%s", $ladiPageKey);
				$jsonString = file_get_contents($url);
				if (!$jsonString) {
					$jsonString = get_web_page($url);
				}

				if ($jsonString) {
					$response = json_decode($jsonString);
					if (isset($response->code) && $response->code == 200) {
						$data = $response->data;
						if (!isset($data->url) || $data->url == '') {
							wp_send_json( array(
								'code'    => 403,
								'message' => __( 'Page URL invalid!' )
							) ); exit;
						}

						$pageId = $this->get_id_by_slug($data->url);
						if (!$pageId) {
							try {
								kses_remove_filters();
								/*$id = wp_insert_post(
									array(
										'post_title'=>$data->title . ' - LadiPage', 
										'post_name'=>$data->url, 
										'post_type'=>'page', 
										'post_content'=> trim($data->html), 
										'post_status' => 'publish',
										'filter' => true ,
										'page_template'  => 'null-template.php'
									)
								);*/
								$query->insert( 
									$query->posts, 
									array(
										'post_title'=> $data->title . ' - LadiPage', 
										'post_name'=> $data->url, 
										'post_type'=> 'page', 
										'post_content'=> trim($data->html), 
										'post_status' => 'publish'
									)
								);

								if ($query && $query->insert_id) {
									$postID = $query->insert_id;
									add_post_meta($postID, '_wp_page_template', 'null-template.php');
									wp_send_json( array(
										'code'    => 200,
										'message' => __( "Publish successfully! Page URL: " . site_url() . '/' . $data->url)
									) ); exit;
								}
							} catch (Exception $ex) {
								wp_send_json( array(
									'code'    => 500,
									'message' => __( $ex->message )
								) ); exit;
							}
							
						} else {
							/*$post = array(
					            'ID' => $pageId,
					            'post_title'=>$data->title . ' - LadiPage', 
					            'post_content' => trim($data->html), 
					        );
							$result = wp_update_post($post, true);*/
							kses_remove_filters();
							$post = array(
								'post_title' => $data->title . ' - LadiPage', 
					            'post_content' => trim($data->html), 
							);

							$where = array(
								'ID' => $pageId
							);
					        kses_remove_filters();
							
							$query->update( $query->posts, $post, $where);
					        if ($query) {
								wp_send_json( array(
										'code'    => 200,
										'message' => __( "Publish successfully! Page URL: " . site_url() . '/' . $data->url)
									) ); exit;
							}
							wp_send_json( array(
								'code'    => 500,
								'message' => __( "Can not update HTML for this Page")
							) ); exit;
						}
					} else {
						wp_send_json( array(
							'code'    => 500,
							'message' => __( $response->message )
						) ); exit;
					}
				}

				wp_send_json( array(
					'code'    => 500,
					'message' => __( "Can not update HTML from this LadiPage Key. Please try publish again" )
				) ); exit;
			}
		}

		public function generateRandomString($length = 10) {
		    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		    $charactersLength = strlen($characters);
		    $randomString = '';
		    for ($i = 0; $i < $length; $i++) {
		        $randomString .= $characters[rand(0, $charactersLength - 1)];
		    }
		    return $randomString;
		}

	}

	function Ladipage() {
		return Ladipage::instance();
	}

	Ladipage();
}

function get_web_page($request, $post = 0) {
	$data = array('message' => '', 'content' => '');

	if (function_exists('curl_exec')) {
		$ch = curl_init();
		if ($post == 1) {
			curl_setopt($ch, CURLOPT_POST,1);
		}
		curl_setopt($ch, CURLOPT_USERAGENT, cp_useragent());
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 5);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_URL, $request);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		$response = curl_exec($ch);
		if (!$response) {
			$data['message'] = 'cURL Error Number ' . curl_errno($ch) . ' : ' . curl_error($ch);
		} else {
			$data['content'] = $response;
		}
		curl_close($ch);
	}

	return $response;
}

function cp_useragent() {
	$blist[] = "Mozilla/5.0 (compatible; Konqueror/4.0; Microsoft Windows) KHTML/4.0.80 (like Gecko)";
	$blist[] = "Mozilla/5.0 (compatible; Konqueror/3.92; Microsoft Windows) KHTML/3.92.0 (like Gecko)";
	$blist[] = "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0; WOW64; SLCC1; .NET CLR 2.0.50727; .NET CLR 3.0.04506; Media Center PC 5.0; .NET CLR 1.1.4322; Windows-Media-Player/10.00.00.3990; InfoPath.2";
	$blist[] = "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.1.4322; InfoPath.1; .NET CLR 2.0.50727; .NET CLR 3.0.04506.30; Dealio Deskball 3.0)";
	$blist[] = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; NeosBrowser; .NET CLR 1.1.4322; .NET CLR 2.0.50727)";

	return $blist[array_rand($blist)];
}
?>