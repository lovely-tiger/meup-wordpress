<?php
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'EL_Booking', false ) ) {
	return new EL_Booking();
}

/**
 * Admin Assets classes
 */
class EL_Booking{


	protected static $_instance = null;

	protected $_prefix = OVA_METABOX_EVENT;

	/**
	 * Constructor
	 */
	public function __construct() {
		require_once EL_PLUGIN_INC . 'ticket/mpdf/vendor/autoload.php';
		require_once EL_PLUGIN_INC. 'ticket/class-el-pdf.php';
	}

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Validate Booking
	 */
	public function validate_before_booking() {
		$data = isset($_POST['data']) ? $_POST['data'] : [];

		$id_event = isset($data['ide']) ? sanitize_text_field( $data['ide'] ) : '';
		$id_cal   = isset($data['idcal']) ? sanitize_text_field( $data['idcal'] ) : '';
		$coupon   = isset($data['coupon']) ? sanitize_text_field( $data['coupon'] ) : '';
		$cart     = isset($data['cart']) ? $data['cart'] : array();

		if ( $data['seat_option'] != 'map' ) {
			$cart = sanitize_cart( $cart );
			return is_ticket_type_exist( $id_event, $id_cal, $cart, $coupon );
		} else {
			$cart = sanitize_cart_map( $cart );
			return is_seat_map_exist( $id_event, $id_cal, $cart, $coupon );
		}
	}

	public function add_booking(){
		$data = isset( $_POST['data']) ? $_POST['data'] : [];

		$id_event	= isset($data['ide']) ? sanitize_text_field( $data['ide'] ) : null;
		$id_cal 	= isset($data['idcal']) ? sanitize_text_field( $data['idcal'] ) : null;


		$seat_option = get_post_meta( $id_event, OVA_METABOX_EVENT.'seat_option', true );

		$first_name = isset($data['first_name']) ? sanitize_text_field( $data['first_name'] ) : null;
		$last_name 	= isset($data['last_name']) ? sanitize_text_field( $data['last_name'] ) : null;
		$name 		= $first_name.' '.$last_name;

		$phone 		= isset($data['phone']) ? sanitize_text_field( $data['phone'] ) : '';
		$address 	= isset($data['address']) ? sanitize_text_field( $data['address'] ) : '';
		$email 		= isset($data['email']) ? sanitize_text_field( $data['email'] ) : null;
		$data_checkout_field = isset( $data['data_checkout_field'] ) ? sanitize_list_checkout_field( $data['data_checkout_field'] ) : [];
		$multiple_ticket 	= isset($data['multiple_ticket']) ? sanitize_text_field( $data['multiple_ticket'] ) : '';
		$data_customers 	= isset($data['data_customers']) ? sanitize_data_customers( $data['data_customers'] ) : array();

		// Files
		$files = isset( $data['files'] ) ? $data['files'] : [];

		if ( ! empty( $files ) && is_array( $files ) ) {
			$list_ckf = get_option( 'ova_booking_form', array() );

			if ( ! empty( $data_checkout_field ) && is_array( $data_checkout_field ) ) {
				foreach ( $data_checkout_field as $ckf_k => $ckf_val ) {
					$ckf_type = isset( $list_ckf[$ckf_k]['type'] ) ? $list_ckf[$ckf_k]['type'] : '';

					if ( $ckf_type === 'file' ) {
						$ckf_url = isset( $files[$ckf_k]['url'] ) ? $files[$ckf_k]['url'] : '';

						if ( $ckf_url ) {
							$data_checkout_field[$ckf_k] = $ckf_url;
						} else {
							$data_checkout_field[$ckf_k] = '';
						}
					}
				}
			}

			if ( ! empty( $data_customers ) && is_array( $data_customers ) ) {
				foreach ( $data_customers as $cus_k => $cus_val ) {
					if ( ! empty( $cus_val ) && is_array( $cus_val ) ) {
						foreach ( $cus_val as $k => $v ) {
							$cus_ckf 	= isset( $v['checkout_fields'] ) ? $v['checkout_fields'] : [];
							$index 		= isset( $v['index'] ) ? $v['index'] : '';

							if ( ! empty( $cus_ckf ) && is_array( $cus_ckf ) ) {
								foreach ( $cus_ckf as $ckf_k => $ckf_val ) {
									$ckf_type = isset( $list_ckf[$ckf_k]['type'] ) ? $list_ckf[$ckf_k]['type'] : '';

									if ( $ckf_type === 'file' ) {
										$index_field = $ckf_k;

										if ( $index ) {
											$index_field .= '_index'.$index;
										}

										$ckf_url = isset( $files[$index_field]['url'] ) ? $files[$index_field]['url'] : '';

										if ( $ckf_url ) {
											$data_customers[$cus_k][$k]['checkout_fields'][$ckf_k] = $ckf_url;
										} else {
											$data_customers[$cus_k][$k]['checkout_fields'][$ckf_k] = '';
										}
									}
								}
							}
						}
					}
				}
			} 
		}

		$coupon         = isset($data['coupon']) ? sanitize_text_field( $data['coupon'] ) : '';
		$payment_method = isset($data['payment_method']) ? sanitize_text_field( $data['payment_method'] ) : null;
		$cart = isset($data['cart']) ? (array)$data['cart'] : [];
		$cart = sanitize_cart($cart);
		$data_extra_service = [];

		// Event Title
		$event_obj = el_get_event( $id_event );

		if( !isset( $event_obj->post_name ) ) return false;
		$title =  $event_obj->post_title;

		// Event Calendar Date
		$date_cal = el_get_calendar_date( $id_event, $id_cal );

		if ( ! $date_cal ) return false;

		$list_type_ticket = get_post_meta( $id_event, OVA_METABOX_EVENT . 'ticket', true);
		$list_choose_seat = [];

		if ( !empty ($list_type_ticket) && is_array($list_type_ticket) ) {
			foreach ($list_type_ticket as $ticket) {
				$list_choose_seat[$ticket['ticket_id']] = $ticket['setup_seat'];
			}
		}

		// get list id_ticket in cart
		$list_id_ticket = $qty_ticket = $seat_booking = [];
		$number_ticket_paid = $number_ticket_free = 0;
		$extra_price = 0;

		if ( ! empty( $cart ) && is_array( $cart ) ) {
			
			foreach ( $cart as $item ) {
				$list_id_ticket[] = $item['id'];
				$qty_ticket[$item['id']] = $item['qty'];

				if ( $list_choose_seat[$item['id']] == 'no' ) {
					$seat_booking[$item['id']] = $this->auto_book_seat_of_ticket($id_event, $id_cal, $item['id'], $item['qty']);
				} else {
					$seat_booking[$item['id']] = $item['seat'];
				}

				if( $item['price'] > 0 ){
					$number_ticket_paid += $item['qty'];
				}else if( $item['price'] == 0 ){
					$number_ticket_free += $item['qty'];
				}


				$extra_service_seat = isset( $item['extra_service'] ) ? $item['extra_service'] : [];
				if ( ! empty( $extra_service_seat ) ) {
					foreach ( $extra_service_seat as $k => $extra_item ) {
						if ( ! empty( $extra_item ) ) {
							foreach ( $extra_item as $j => $val ) {
								$qty = (int)$val['qty'];
								$price = (float)$val['price'];
								$extra_price += $price * $qty;

							}
						}
					}
					$data_extra_service[$item['id']] = $extra_service_seat;
				}

			}
		}

		$total_before_tax = apply_filters( 'el_total', get_total($id_event, $cart, $coupon) );
		$total_before_tax += $extra_price;
		$total_discount = EL_Cart::instance()->el_get_total_discount( $id_event, $cart, $coupon );

		// System fee
		$system_fee_type 	= EL()->options->tax_fee->get('type_system_fee', 'percent');
		$percent_system_fee = EL()->options->tax_fee->get('percent_system_fee');
		$fixed_system_fee 	= EL()->options->tax_fee->get('fixed_system_fee');

		$system_fee = 0;

		if ( $payment_method != 'free' && $total_before_tax > 0 ) {

			if ( ( $system_fee_type === 'both' || $system_fee_type === 'percent' ) && $percent_system_fee ) {
				$system_fee += $total_before_tax*( (float) $percent_system_fee / 100 );
			}
			
			if ( ( $system_fee_type === 'both' || $system_fee_type === 'amount' ) && $fixed_system_fee ) {
				$system_fee += (float) $fixed_system_fee;
			}
		}

		$total_after_tax = apply_filters( 'el_total_after_tax', get_total_after_tax($total_before_tax, $id_event) );

		$commission = get_commission_admin( $id_event, $total_before_tax, $number_ticket_paid, $number_ticket_free );

		$profit_included_tax = EL()->options->tax_fee->get('profit_included_tax');
		if ( $profit_included_tax == 'yes' ) {
			$profit = $total_after_tax - $commission;	
		} else {
			$profit = $total_before_tax - $commission;
		}
		
		$profit = apply_filters( 'el_get_profit_booking', $profit, $id_event, $total_before_tax, $total_after_tax, $commission );

		$tax = $total_after_tax - $total_before_tax;

		$event_obj = get_post( $id_event );
		$event_author_id = $event_obj->post_author;
		
		$post_data['post_type'] = 'el_bookings';
		$post_data['post_title'] = $title;
		$post_data['post_status'] = 'publish';
		$post_data['post_name'] = $title;
		$post_data['post_author'] = $event_author_id;

		$id_customer = get_current_user_id();

		// Plus system fee

		if ( $system_fee ) {
			$total_before_tax += $system_fee;
			$total_after_tax += $system_fee;
			$commission += $system_fee;
		}
		
    	// Order id is empty
		if ( !$id_event || !$id_cal || !$name || !$email || !$cart || !$payment_method ) {
			return false;
		}

		$meta_input = array(
			$this->_prefix.'id_event' 		=> $id_event,
			$this->_prefix.'id_cal' 		=> $id_cal,
			$this->_prefix.'title_event' 	=> $title,
			$this->_prefix.'date_cal' 		=> date_i18n( get_option( 'date_format'), strtotime( $date_cal ) ) ,
			$this->_prefix.'date_cal_tmp' 	=> strtotime($date_cal),
			$this->_prefix.'name' 			=> $name,
			$this->_prefix.'first_name' 	=> $first_name,
			$this->_prefix.'last_name' 		=> $last_name,
			$this->_prefix.'phone' 			=> $phone,
			$this->_prefix.'email' 			=> $email,
			$this->_prefix.'address' 		=> $address,
			$this->_prefix.'data_checkout_field' => json_encode( $data_checkout_field, JSON_UNESCAPED_UNICODE ),
			$this->_prefix.'coupon' 						=> $coupon,
			$this->_prefix.'discount' 						=> floatval( $total_discount ),
			$this->_prefix.'payment_method' 				=> $payment_method,
			$this->_prefix.'cart' 							=> $cart,
			$this->_prefix.'seat_option' 					=> $seat_option,
			$this->_prefix.'extra_service' 					=> $data_extra_service,
			$this->_prefix.'list_id_ticket' 				=> json_encode( $list_id_ticket, JSON_UNESCAPED_UNICODE ),
			$this->_prefix.'list_qty_ticket_by_id_ticket' 	=> $qty_ticket,
			$this->_prefix.'list_seat_book' 				=> $seat_booking,
			$this->_prefix.'total' 							=> $total_before_tax,
			$this->_prefix.'total_after_tax' 				=> $total_after_tax,
			$this->_prefix.'commission' 					=> $commission,
			$this->_prefix.'profit' 						=> $profit,
			$this->_prefix.'tax' 							=> $tax,
			$this->_prefix.'status' 						=> 'Pending',
			$this->_prefix.'id_customer' 					=> $id_customer,
			$this->_prefix.'profit_status' 					=> '',
			$this->_prefix.'orderid' 						=> '',
			$this->_prefix.'system_fee' 					=> $system_fee,
			$this->_prefix.'multiple_ticket' 				=> $multiple_ticket,
			$this->_prefix.'data_customers' 				=> $data_customers,
			$this->_prefix.'time_countdown_checkout' 		=> current_time( 'timestamp' ),
			$this->_prefix.'status_holding_ticket' 			=> 'Pending',
		);


		$post_data['meta_input'] = apply_filters( 'el_booking_metabox_input', $meta_input );
		
		if( $booking_id = wp_insert_post( $post_data, true ) ){
			//update title booking
			$arr_post = [
				'ID' => $booking_id,
				'post_title' => $booking_id . ' - ' . $title,
			];
			wp_update_post($arr_post);

			return $booking_id;
			wp_die();

		}else{
			return;
			wp_die();
		}
	}

	public function add_booking_map() {
		$data 			= isset( $_POST['data'] ) ? $_POST['data'] : [];
		$id_event 		= isset( $data['ide'] ) ? sanitize_text_field( $data['ide'] ) : null;
		$seat_option 	= get_post_meta( $id_event, OVA_METABOX_EVENT.'seat_option', true );
		$id_cal 		= isset( $data['idcal'] ) ? sanitize_text_field( $data['idcal'] ) : null;
		$first_name 	= isset( $data['first_name'] ) ? sanitize_text_field( $data['first_name'] ) : null;
		$last_name 		= isset( $data['last_name'] ) ? sanitize_text_field( $data['last_name'] ) : null;
		$name 			= $first_name.' '.$last_name;

		$data_checkout_field = isset( $data['data_checkout_field'] ) ? sanitize_list_checkout_field( $data['data_checkout_field'] ) : [];
		$multiple_ticket 	= isset( $data['multiple_ticket'] ) ? sanitize_text_field( $data['multiple_ticket'] ) : '';
		$data_customers 	= isset( $data['data_customers'] ) ? sanitize_data_customers( $data['data_customers'] ) : array();
		
		$phone 		= isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '';
		$address 	= isset( $data['address'] ) ? sanitize_text_field( $data['address'] ) : '';
		$email 		= isset( $data['email'] ) ? sanitize_text_field( $data['email'] ) : null;
		$coupon 	= isset( $data['coupon'] ) ? sanitize_text_field( $data['coupon'] ) : '';
		$payment_method = isset( $data['payment_method'] ) ? sanitize_text_field( $data['payment_method'] ) : null;
		$cart = isset( $data['cart'] ) ? (array)$data['cart'] : [];
		$cart = sanitize_cart_map($cart);
		$data_extra_service = [];
		// Event Title
		$event_obj = el_get_event( $id_event );

		if ( ! isset( $event_obj->post_name ) ) return false;
		$title = $event_obj->post_title;

		// Event Calendar Date
		$date_cal = el_get_calendar_date( $id_event, $id_cal );
		if ( ! $date_cal ) return false;

		// get list id_ticket in cart
		$list_id_ticket = $qty_ticket = $seat_booking = $arr_seat = $arr_area = [];
		$number_ticket_paid = $number_ticket_free = 0;
		$extra_price = 0;

		if ( ! empty( $cart ) && is_array( $cart ) ) {
			foreach ( $cart as $item ) {
				$list_id_ticket[] 	= $item['id'];
				$seat_booking[] 	= $item['id'];
				$item_qty 			= isset( $item['qty'] ) ? absint( $item['qty'] ) : 0;
				$item_price 		= isset( $item['price'] ) ? (float)$item['price'] : 0;
				$extra_service 		= isset( $item['extra_service'] ) ? $item['extra_service'] : [];

				// area + person_type
				if ( $item_qty || isset( $item['data_person'] ) ) {

					if ( isset( $item['data_person'] ) ) {
						foreach ( $item['data_person'] as $key => $value ) {
							$per_extra_service = isset( $value['extra_service'] ) ? $value['extra_service'] : [];
							$item_qty += (int) $value['qty'];

							// Add extra service
							if ( ! empty( $per_extra_service ) ) {

								if ( empty( $data_extra_service[$item['id']] ) ) {
									$data_extra_service[$item['id']] = $per_extra_service;
								} else {
									$data_extra_service[$item['id']] = array_merge( $data_extra_service[$item['id']], $per_extra_service );
								}
								
								foreach ( $per_extra_service as $k => $val ) {

									if ( ! empty( $val ) && is_array( $val ) ) {
										foreach ($val as $m => $n) {
											$price = isset( $n['price'] ) ? (float)$n['price'] : 0;
											$qty = isset( $n['qty'] ) ? (int)$n['qty'] : 0;
											$extra_price += $price * $qty;
										}
									}
									
								}
							}

						}
					}
					$qty_ticket[$item['id']] = $item_qty;

					// Add array Area
					array_push( $arr_area, $item['id']);

					// Add extra service
					if ( ! empty( $extra_service ) ) {
						$data_extra_service[$item['id']] = $extra_service;
						foreach ( $extra_service as $k => $val ) {
							$price = isset( $val['price'] ) ? (float)$val['price'] : 0;
							$qty = isset( $val['qty'] ) ? (int)$val['qty'] : 0;
							$extra_price += $price * $qty;
						}
					}

				} else {
					// Seat
					$qty_ticket[$item['id']] = 1;
					
					// Add array Seat
					array_push( $arr_seat, $item['id']);

					// add extra service
					
					if ( ! empty( $extra_service ) ) {
						$data_extra_service[$item['id']][] = $extra_service;
						foreach ( $extra_service as $k => $val ) {
							$price = isset( $val['price'] ) ? (float)$val['price'] : 0;
							$qty = isset( $val['qty'] ) ? absint( $val['qty'] ) : 0;
							$extra_price += $price * $qty;
						}
					}
				}

				if ( $item['price'] > 0 ) {
					$number_ticket_paid += $item_qty ? $item_qty : 1;
				} elseif ( $item['price'] == 0 ) {
					$number_ticket_free += $item_qty ? $item_qty : 1;
				}

			}
		}
		
		$total_before_tax 	= apply_filters( 'el_total', get_total( $id_event, $cart, $coupon ) );
		$total_before_tax 	+= $extra_price;
		$total_discount 	= EL_Cart::instance()->el_get_total_discount( $id_event, $cart, $coupon );

		// System fee
		$system_fee_type 	= EL()->options->tax_fee->get('type_system_fee', 'percent');
		$percent_system_fee = EL()->options->tax_fee->get('percent_system_fee');
		$fixed_system_fee 	= EL()->options->tax_fee->get('fixed_system_fee');
		$system_fee 		= 0;

		if ( $payment_method != 'free' && $total_before_tax > 0 ) {

			if ( ( $system_fee_type === 'both' || $system_fee_type === 'percent' ) && $percent_system_fee ) {
				$system_fee += $total_before_tax*( (float) $percent_system_fee / 100 );
			}
			
			if ( ( $system_fee_type === 'both' || $system_fee_type === 'amount' ) && $fixed_system_fee ) {
				$system_fee += (float) $fixed_system_fee;
			}
		}

		$total_after_tax = apply_filters( 'el_total_after_tax', get_total_after_tax( $total_before_tax, $id_event ) );

		$commission = 0;
		if ( $total_before_tax > 0 ) {
			$commission = get_commission_admin( $id_event, $total_before_tax, $number_ticket_paid, $number_ticket_free );
		}
		
		$profit = 0;
		$profit_included_tax = EL()->options->tax_fee->get('profit_included_tax');

		if ( $total_before_tax > 0 ) {

			if ( $profit_included_tax == 'yes' ) {
				$profit = $total_after_tax - $commission;	
			} else {
				$profit = $total_before_tax - $commission;
			}
		
			$profit = apply_filters( 'el_get_profit_booking', $profit, $id_event, $total_before_tax, $total_after_tax, $commission );
		}

		$tax = $total_after_tax - $total_before_tax;
		
		$event_obj = get_post( $id_event );
		$event_author_id = $event_obj->post_author;
		
		$post_data['post_type'] 	= 'el_bookings';
		$post_data['post_title'] 	= $title;
		$post_data['post_status'] 	= 'publish';
		$post_data['post_name'] 	= $title;
		$post_data['post_author'] 	= $event_author_id;

		$id_customer = get_current_user_id();

		// Plus system fee
		if ( $system_fee ) {
			$total_before_tax += $system_fee;
			$total_after_tax += $system_fee;
			$commission += $system_fee;
		}

		// Custom checkout fields: type = file
		$files = isset( $data['files'] ) ? $data['files'] : [];

		if ( ! empty( $files ) && is_array( $files ) ) {
			$list_ckf = get_option( 'ova_booking_form', array() );

			if ( ! empty( $data_checkout_field ) && is_array( $data_checkout_field ) ) {
				foreach ( $data_checkout_field as $ckf_k => $ckf_val ) {
					$ckf_type = isset( $list_ckf[$ckf_k]['type'] ) ? $list_ckf[$ckf_k]['type'] : '';

					if ( $ckf_type === 'file' ) {
						$ckf_url = isset( $files[$ckf_k]['url'] ) ? $files[$ckf_k]['url'] : '';

						if ( $ckf_url ) {
							$data_checkout_field[$ckf_k] = $ckf_url;
						}
					}
				}
			}

			if ( ! empty( $data_customers ) && is_array( $data_customers ) ) {
				foreach ( $data_customers as $cus_k => $cus_val ) {
					if ( ! empty( $cus_val ) && is_array( $cus_val ) ) {
						if ( $cus_k && in_array( $cus_k, $arr_area ) ) {
							foreach ( $cus_val as $k => $v ) {
								$cus_ckf 	= isset( $v['checkout_fields'] ) ? $v['checkout_fields'] : [];
								$index 		= isset( $v['index'] ) ? $v['index'] : '';

								if ( ! empty( $cus_ckf ) && is_array( $cus_ckf ) ) {
									foreach ( $cus_ckf as $ckf_k => $ckf_val ) {
										$ckf_type = isset( $list_ckf[$ckf_k]['type'] ) ? $list_ckf[$ckf_k]['type'] : '';

										if ( $ckf_type === 'file' ) {
											$index_field = $ckf_k;

											if ( $index ) {
												$index_field .= '_index'.$index;
											}

											$ckf_url = isset( $files[$index_field]['url'] ) ? $files[$index_field]['url'] : '';

											if ( $ckf_url ) {
												$data_customers[$cus_k][$k]['checkout_fields'][$ckf_k] = $ckf_url;
											} else {
												$data_customers[$cus_k][$k]['checkout_fields'][$ckf_k] = '';
											}
										}
									}
								}
							}
						} else {
							$cus_ckf 	= isset( $cus_val['checkout_fields'] ) ? $cus_val['checkout_fields'] : [];
							$index 		= isset( $cus_val['index'] ) ? $cus_val['index'] : '';

							if ( ! empty( $cus_ckf ) && is_array( $cus_ckf ) ) {
								foreach ( $cus_ckf as $ckf_k => $ckf_val ) {
									$ckf_type = isset( $list_ckf[$ckf_k]['type'] ) ? $list_ckf[$ckf_k]['type'] : '';

									if ( $ckf_type === 'file' ) {
										$index_field = $ckf_k;

										if ( $index ) {
											$index_field .= '_index'.$index;
										}

										$ckf_url = isset( $files[$index_field]['url'] ) ? $files[$index_field]['url'] : '';

										if ( $ckf_url ) {
											$data_customers[$cus_k]['checkout_fields'][$ckf_k] = $ckf_url;
										}
									}
								}
							}
						}
					}
				}
			} 
		}

    	// Order id is empty
		if ( ! $id_event || ! $id_cal || ! $name || ! $email || ! $cart || ! $payment_method ) {
			return false;
		}

		$meta_input = array(
			$this->_prefix.'id_event' 				=> $id_event,
			$this->_prefix.'id_cal' 				=> $id_cal,
			$this->_prefix.'title_event' 			=> $title,
			$this->_prefix.'date_cal' 				=> date_i18n( get_option( 'date_format'), strtotime( $date_cal ) ) ,
			$this->_prefix.'date_cal_tmp' 			=> strtotime($date_cal),
			$this->_prefix.'name' 					=> $name,
			$this->_prefix.'first_name' 			=> $first_name,
			$this->_prefix.'last_name' 				=> $last_name,
			$this->_prefix.'phone' 					=> $phone,
			$this->_prefix.'email' 					=> $email,
			$this->_prefix.'address' 				=> $address,
			$this->_prefix.'data_checkout_field' 	=> json_encode( $data_checkout_field, JSON_UNESCAPED_UNICODE ),
			$this->_prefix.'coupon' 				=> $coupon,
			$this->_prefix.'discount' 				=> floatval( $total_discount ),
			$this->_prefix.'payment_method' 		=> $payment_method,
			$this->_prefix.'cart' 					=> $cart,
			$this->_prefix.'arr_seat' 				=> $arr_seat,
			$this->_prefix.'arr_area' 				=> $arr_area,
			$this->_prefix.'cart' 					=> $cart,
			$this->_prefix.'extra_service' 			=> $data_extra_service,
			$this->_prefix.'seat_option' 			=> $seat_option,
			$this->_prefix.'list_id_ticket' 		=> json_encode($list_id_ticket, JSON_UNESCAPED_UNICODE),
			$this->_prefix.'list_qty_ticket_by_id_ticket' => $qty_ticket,
			$this->_prefix.'list_seat_book' 			=> $seat_booking,
			$this->_prefix.'total' 						=> $total_before_tax,
			$this->_prefix.'total_after_tax' 			=> $total_after_tax,
			$this->_prefix.'commission' 				=> $commission,
			$this->_prefix.'profit' 					=> $profit,
			$this->_prefix.'tax' 						=> $tax,
			$this->_prefix.'status' 					=> 'Pending',
			$this->_prefix.'id_customer' 				=> $id_customer,
			$this->_prefix.'profit_status' 				=> '',
			$this->_prefix.'orderid' 					=> '',
			$this->_prefix.'system_fee' 				=> $system_fee,
			$this->_prefix.'multiple_ticket' 			=> $multiple_ticket,
			$this->_prefix.'data_customers' 			=> $data_customers,
			$this->_prefix.'time_countdown_checkout' 	=> current_time( 'timestamp' ),
			$this->_prefix.'status_holding_ticket' 		=> 'Pending',
		);

		$post_data['meta_input'] = apply_filters( 'el_booking_metabox_input', $meta_input );
		
		if( $booking_id = wp_insert_post( $post_data, true ) ){
			//update title booking
			$arr_post = [
				'ID' => $booking_id,
				'post_title' => $booking_id . ' - ' . $title,
			];
			wp_update_post($arr_post);

			return $booking_id;
			wp_die();

		}else{
			return;
			wp_die();
		}
	}

	public function update_booking( $post ){
	}

	public function el_get_booking( $id ){
		if( !$id ) return false;
		return get_post( $id );
	}

	public function get_number_ticket_total( $id_event = null, $id_cal = null, $id_ticket = null ) {
        if ( $id_ticket == null || $id_cal == null || $id_event == null ) return 0;

		//get total ticket in event
		$list_ticket_in_event = get_post_meta( $id_event, OVA_METABOX_EVENT . 'ticket', true);

		$total = 0;

		if ( ! empty( $list_ticket_in_event ) && is_array( $list_ticket_in_event ) ) {
			foreach ( $list_ticket_in_event as $ticket ) {
				if ( $ticket['ticket_id'] == $id_ticket ) {
					$ticket_max = get_post_meta( $id_event, OVA_METABOX_EVENT.'ticket_max['.$id_cal.'_'.$id_ticket.']', true) !='' ? get_post_meta( $id_event, OVA_METABOX_EVENT.'ticket_max['.$id_cal.'_'.$id_ticket.']', true) :'';
					if ( $ticket_max !='' ) {
						$total = $ticket_max;
					} else {
						$total = isset( $ticket['number_total_ticket'] ) ? (int)$ticket['number_total_ticket'] : 0;
					}
					
					break;
				}
			}
		}

		return $total;
	}

	public function get_number_ticket_map_total( $id_event = null ) {
		if ( $id_event == null) return 0;

		$current_time = el_get_current_time_by_event( $id_event );

		//get total ticket in event
		$ticket_map = get_post_meta( $id_event, OVA_METABOX_EVENT . 'ticket_map', true );
		$start_time = $end_time = 0;

		if ( isset( $ticket_map['start_ticket_date'] ) && isset( $ticket_map['start_ticket_time'] ) ) {
			$start_time = el_get_time_int_by_date_and_hour( $ticket_map['start_ticket_date'], $ticket_map['start_ticket_time'] );
		}

		if ( isset( $ticket_map['close_ticket_date'] ) && isset( $ticket_map['close_ticket_time'] ) ) {
			$end_time = el_get_time_int_by_date_and_hour( $ticket_map['close_ticket_date'], $ticket_map['close_ticket_time'] );
		}

		$total = 0;

		if ( isset( $ticket_map['seat'] ) && ! empty( $ticket_map['seat'] ) && is_array( $ticket_map['seat'] ) ) {
			foreach ( $ticket_map['seat'] as $ticket_seat ) {
				$seat_start_time 	= $start_time;
				$seat_end_time 		= $end_time;

				if ( isset( $ticket_seat['start_date'] ) && isset( $ticket_seat['start_time'] ) ) {
					$seat_start_time = el_get_time_int_by_date_and_hour( $ticket_seat['start_date'], $ticket_seat['start_time'] );
				}

				if ( isset( $ticket_seat['end_date'] ) && isset( $ticket_seat['end_time'] ) ) {
					$seat_end_time = el_get_time_int_by_date_and_hour( $ticket_seat['end_date'], $ticket_seat['end_time'] );
				}

				if ( $current_time < $seat_end_time && $current_time >= $seat_start_time ) {
					$seat 	= explode( ',', $ticket_seat['id'] );
					$total += count( $seat );
				}
			}
		}

		if ( isset( $ticket_map['area'] ) && ! empty( $ticket_map['area'] ) && is_array( $ticket_map['area'] ) ) {
			foreach ( $ticket_map['area'] as $ticket_area ) {
				$seat_start_time 	= $start_time;
				$seat_end_time 		= $end_time;

				if ( isset( $ticket_area['start_date'] ) && isset( $ticket_area['start_time'] ) ) {
					$seat_start_time = el_get_time_int_by_date_and_hour( $ticket_area['start_date'], $ticket_area['start_time'] );
				}

				if ( isset( $ticket_area['end_date'] ) && isset( $ticket_area['end_time'] ) ) {
					$seat_end_time = el_get_time_int_by_date_and_hour( $ticket_area['end_date'], $ticket_area['end_time'] );
				}

				if ( $current_time < $seat_end_time && $current_time >= $seat_start_time ) {
					$total += absint( $ticket_area['qty'] );
				}
			}
		}
		
		return $total;
	}

	public function get_number_ticket_booked( $id_event = null, $id_cal = null, $id_ticket = null ) {
		if ( $id_ticket == null || $id_cal == null || $id_event == null ) return 0;

		$event_ids = el_get_product_ids_multi_lang( $id_event );

		$args = [
			'post_type' 		=> 'el_bookings',
			'post_status' 		=> 'publish',
			'fields'			=> 'ids',
			'posts_per_page' 	=> -1,
			'numberposts' 		=> -1,
			'nopaging' 			=> true,
			'meta_query' 		=> [
				'relation' 		=> 'AND',
				[
					'key' 		=> $this->_prefix . 'id_event',
					'value' 	=> $event_ids,
					'compare' 	=> 'IN',
				],
				[
					'key' 		=> $this->_prefix . 'id_cal',
					'value' 	=> $id_cal,
				],
				[
					'key' 		=> $this->_prefix . 'status',
					'value' 	=> 'Completed',
				]
			],
			
			
		];

		$bookings = get_posts($args);

		//get total booked
		$total_booked = 0;

		if ( ! empty( $bookings ) && is_array( $bookings ) ) {
			foreach ( $bookings as $booking_id ) {
				$ticket_in_booking = get_post_meta( $booking_id, OVA_METABOX_EVENT . 'list_qty_ticket_by_id_ticket', true );

				if ( is_array( $ticket_in_booking ) && array_key_exists( (string)$id_ticket, $ticket_in_booking ) ) {
					$total_booked += absint( $ticket_in_booking[$id_ticket] );
				}

			}
		}
		
		return $total_booked;
	}

	public function get_number_ticket_map_booked( $id_event = null, $id_cal = null ) {
		if ( $id_cal == null || $id_event == null ) return 0;

		$event_ids = el_get_product_ids_multi_lang( $id_event );

		$args = [
			'post_type' 		=> 'el_bookings',
			'post_status' 		=> 'publish',
			'fields'			=> 'ids',
			'posts_per_page' 	=> -1,
			'numberposts' 		=> -1,
			'nopaging' 			=> true,
			'meta_query' 		=> [
				'relation' 		=> 'AND',
				[
					'key' 		=> $this->_prefix . 'id_event',
					'value' 	=> $event_ids,
					'compare' 	=> 'IN',
				],
				[
					'key' 		=> $this->_prefix . 'id_cal',
					'value' 	=> $id_cal,
				],
				[
					'key' 		=> $this->_prefix . 'status',
					'value' 	=> 'Completed',
				]
			]
			
		];

		$bookings = get_posts( $args );

		//get total booked
		$total_booked = 0;

		if ( ! empty( $bookings ) && is_array( $bookings ) ) {
			foreach ( $bookings as $booking_id ) {
				$qty_ticket = get_post_meta( $booking_id, OVA_METABOX_EVENT . 'list_qty_ticket_by_id_ticket', true );

				foreach ( $qty_ticket as $qty ) {
					$total_booked += absint( $qty );
				}
			}
		}
		
		return $total_booked;
	}

	public function get_number_ticket_rest ( $id_event = null, $id_cal = null, $id_ticket = null ) {
		if ( $id_ticket == null || $id_cal == null || $id_event == null ) return 0;

		$total = $this->get_number_ticket_total($id_event, $id_cal, $id_ticket);

        $total_booked = $this->get_number_ticket_booked($id_event, $id_cal, $id_ticket);
   
		$total_rest = $total - $total_booked;
		
		return $total_rest;
	}

	public function get_number_ticket_map_rest( $id_event = null, $id_cal = null ) {
		if (  $id_cal == null || $id_event == null ) return 0;

		$total 			= $this->get_number_ticket_map_total( $id_event );
		$total_booked 	= $this->get_number_ticket_map_booked( $id_event, $id_cal );
		$total_rest 	= $total - $total_booked;

		if ( ! $total_rest || (int)$total_rest < 0 ) $total_rest = 0;
		
		return $total_rest;
	}

	public function get_number_coupon_code_used ( $id_event = null, $coupon_code = null ) {
		if ( $id_event == null || $coupon_code == null ) return 0;
		
		$seat_option = get_post_meta( $id_event, OVA_METABOX_EVENT . 'seat_option', true);
		$ticket_map = get_post_meta( $id_event, OVA_METABOX_EVENT . 'ticket_map', true);
		
		$bookings = get_posts([
			'post_type' => 'el_bookings',
			'post_status' => 'publish',
			'meta_key' => $this->_prefix . 'id_event',
			'meta_value' => $id_event,
			'posts_per_page' => -1, 
			'numberposts' => -1,
			'nopaging' => true,
		]);
		if ( $seat_option != 'map' ) {
			$coupons = get_post_meta( $id_event, OVA_METABOX_EVENT . 'coupon', true);
			$list_id_ticket_has_coupon = [];
			if (!empty($coupons) && is_array($coupons)) {
				foreach ($coupons as $coupon) {
					if ( $coupon['discount_code'] == $coupon_code ) {
						$list_id_ticket_has_coupon = isset( $coupon['list_ticket'] ) ? $coupon['list_ticket'] : '';
					}
				}
			}
		} else {
			$list_id_ticket_has_coupon = [];
			if ( !empty( $ticket_map['seat'] ) && is_array( $ticket_map['seat'] ) ) {
				foreach ( $ticket_map['seat'] as $value ) {
					$list_id_ticket_has_coupon[] = $value['id'];
				}
			}
		}
		

		$total = 0;
		if ( ! empty($bookings) && is_array($bookings) ) {
			foreach( $bookings as $booking ) {

				$coupon_in_booking = get_post_meta( $booking->ID, OVA_METABOX_EVENT . 'coupon', true );
				$list_id_ticket_in_booking = json_decode( get_post_meta( $booking->ID, OVA_METABOX_EVENT . 'list_id_ticket', true ) );
				
				if (!empty($list_id_ticket_in_booking) && is_array($list_id_ticket_in_booking)) {
					foreach ($list_id_ticket_in_booking as $value) {
						if ( $value != '' && !empty( $list_id_ticket_has_coupon ) && in_array($value, $list_id_ticket_has_coupon) ) {
							if ( $coupon_code == $coupon_in_booking ) {
								$total += 1;
							}
						}
					}
				}
			}
		}

		return $total;
	}

	public function auto_book_seat_of_ticket ( $id_event = null, $id_cal = null, $id_ticket = null, $qty = 0 ) {
		$seat_option = get_seat_option( $id_event );
		if ( $id_ticket == null || $id_cal == null || $id_event == null || $seat_option != 'simple' || $qty <= 0 ) return [];

		//get total ticket and all list seat
		$list_type_ticket = get_post_meta( $id_event, OVA_METABOX_EVENT . 'ticket', true);
		$total_ticket = 0;
		$total_seat_list = '';
		if (!empty($list_type_ticket) && is_array($list_type_ticket)) {
			foreach($list_type_ticket as $ticket) {
				if ($ticket['ticket_id'] == $id_ticket) {
					$total_ticket = (int)$ticket['number_total_ticket'];
					$total_seat_list = $ticket['seat_list'];
				}
			}
		}

		if (empty($total_seat_list)) return [];
		
		//change seat list string => array
		$arr_total_seat_list = explode(',', $total_seat_list);

		//get list seat booked
		$list_seat_booked_by_ticket = $this->get_list_seat_booked( $id_event, $id_cal, $id_ticket);

		if ( !is_checkout() ) {
			// Get list seat holding ticket
			$list_seat_by_holding_ticket = $this->get_list_seat_by_holding_ticket( $id_event, $id_cal );

			$list_seat_booked_by_ticket = array_unique( array_merge( $list_seat_booked_by_ticket, $list_seat_by_holding_ticket ) );
		}
		
		//loop total seat if value in total seat does not exist in list seat booked push item to array number == qty
		$list_seat_add_booking = [];
		if ( ! empty($arr_total_seat_list) && is_array($arr_total_seat_list) && $total_ticket > 0 && is_array($list_seat_booked_by_ticket) ) {
			$j = 0;
			for ( $i = 0; $i < $total_ticket; $i++ ) {

				if ( ! in_array( trim($arr_total_seat_list[$i]), $list_seat_booked_by_ticket) ) {
					$list_seat_add_booking[] = trim($arr_total_seat_list[$i]);
					$j++;
				}
				if ( $j == $qty ) break;
			}
		}

		return $list_seat_add_booking;
	}

	public function get_list_seat_booked( $id_event = null, $id_cal = null, $id_ticket = null ) {
		$seat_option = get_seat_option( $id_event );

		if ( $id_event == null || $id_cal == null || $id_ticket == null || $seat_option != 'simple' ) return [];

		$event_ids = el_get_product_ids_multi_lang( $id_event );

		$args = [
			'post_type' 	=> 'el_bookings',
			'post_status' 	=> 'publish',
			'meta_query' 	=> [
				'relation' 	=> 'AND',
				[
					'key' 		=> $this->_prefix . 'id_event',
					'value' 	=> $event_ids,
					'compare' 	=> 'IN',
				],
				[
					'key' 		=> $this->_prefix . 'id_cal',
					'value' 	=> $id_cal,
				],
				[
					'key' 		=> $this->_prefix . 'status',
					'value' 	=> 'Completed',
				]
			],

			'posts_per_page' 	=> -1, 
			'numberposts' 		=> -1,
			'nopaging' 			=> true,
		];

		$bookings = get_posts( $args );

		//get list seat booked in event and in one day (id_cal)
		$list_seat_booked_by_ticket = [];

		if ( is_numeric( $id_ticket ) ) {
			$id_ticket = (int) $id_ticket;
		}

		if ( !empty($bookings) && is_array($bookings) ) {
			foreach ( $bookings as $booking ) {
				$all_seat_booked = get_post_meta( $booking->ID, OVA_METABOX_EVENT . 'list_seat_book', true );

				if ( is_array( $all_seat_booked ) && array_key_exists( $id_ticket, $all_seat_booked ) ) {
					foreach ( $all_seat_booked[$id_ticket] as $value) {
						$list_seat_booked_by_ticket[] = $value;
					}
				}
			}
		}

		return $list_seat_booked_by_ticket;
	}

	public function get_list_seat_by_holding_ticket( $id_event = null, $id_cal = null ) {
		$seat_option = get_seat_option( $id_event );

		if ( $id_event == null || $id_cal == null || $seat_option != 'simple' ) return [];

		$event_ids = el_get_product_ids_multi_lang( $id_event );
		$time_countdown_checkout = intval( EL()->options->checkout->get('max_time_complete_checkout', 600) );

		$args = [
			'post_type' 		=> 'holding_ticket',
			'post_status' 		=> 'publish',
			'posts_per_page' 	=> -1,
			'fields' 			=> 'ids',
			'meta_query' 		=> [
				'relation' 		=> 'AND',
				[
					'key' 		=> $this->_prefix . 'id_event',
					'value' 	=> $event_ids,
					'compare' 	=> 'IN',
				],
				[
					'key' 		=> $this->_prefix . 'id_cal',
					'value' 	=> $id_cal,
					'compare' 	=> '=',
				],
			],
		];

		$holding_ticket = get_posts( $args );

		$current_time = current_time( 'timestamp' );

		//get list seat booked in event and in one day (id_cal)
		$list_seat_by_holding_ticket = [];
		if ( !empty( $holding_ticket ) && is_array( $holding_ticket ) ) {
			foreach( $holding_ticket as $ht_id ) {
				$seat = get_post_meta( $ht_id, OVA_METABOX_EVENT . 'seat', true );
				$time_sumbit_checkout = get_post_meta( $ht_id, OVA_METABOX_EVENT . 'current_time', true );
				$past_time = absint( $current_time ) - absint( $time_sumbit_checkout );

				if ( $past_time < $time_countdown_checkout ) {
					$list_seat_by_holding_ticket[] = $seat;
				}
			}
		}

		return $list_seat_by_holding_ticket;
	}

	public function get_list_seat_map_booked( $id_event = null, $id_cal = null ) {
		if ( $id_event == null || $id_cal == null ) return [];

		$event_ids = el_get_product_ids_multi_lang( $id_event );

		$args = [
			'post_type' 	=> 'el_bookings',
			'post_status' 	=> 'publish',
			'meta_query' 	=> [
				'relation' 	=> 'AND',
				[
					'key' 		=> $this->_prefix . 'id_event',
					'value' 	=> $event_ids,
					'compare' 	=> 'IN',
				],
				[
					'key' 	=> $this->_prefix . 'id_cal',
					'value' => $id_cal,
				],
				[
					'key' 	=> $this->_prefix . 'status',
					'value' => apply_filters( 'el_bookings_status_seat_map_booked', 'Completed' ),
				]
			],

			'posts_per_page' 	=> -1, 
			'numberposts' 		=> -1,
			'nopaging' 			=> true,
		];

		$bookings = get_posts( $args );

		// Get list seat booked in event and in one day (id_cal)
		$list_seat_booked_by_ticket = [];

		if ( ! empty( $bookings ) && is_array( $bookings ) ) {
			$area_available = $this->el_get_area_qty_available( $id_event, $id_cal );

			foreach ( $bookings as $booking ) {
				$all_seat_booked = get_post_meta( $booking->ID, OVA_METABOX_EVENT . 'list_seat_book', true ) ? get_post_meta( $booking->ID, OVA_METABOX_EVENT . 'list_seat_book', true ) : array();
				$qty_seat_booked = get_post_meta( $booking->ID, OVA_METABOX_EVENT . 'list_qty_ticket_by_id_ticket', true ) ? get_post_meta( $booking->ID, OVA_METABOX_EVENT . 'list_qty_ticket_by_id_ticket', true ) : array();

				foreach ( $all_seat_booked as $id ) {
					if ( is_string( $id ) || is_numeric($id ) ) {
						if ( array_key_exists( $id, $area_available ) ) {
							if ( absint( $area_available[$id] ) <= 0 ) {
								$list_seat_booked_by_ticket[] = $id;
							}
						} else {
							$list_seat_booked_by_ticket[] = $id;
						}
					}
				}
			}
		}

		return $list_seat_booked_by_ticket;
	}

	public function get_list_seat_rest( $id_event = null, $id_cal = null, $id_ticket = null ) {
		$seat_option = get_seat_option( $id_event );

		if ( $id_event == null || $id_cal == null || $id_ticket == null || $seat_option != 'simple' ) return [];

		//get list seat booked in event and in one day (id_cal)
		$list_seat_booked_by_ticket = $this->get_list_seat_booked( $id_event, $id_cal, $id_ticket);
		

		//get list all seat by id event and id ticket
		$list_ticket_event = get_post_meta( $id_event, OVA_METABOX_EVENT . 'ticket', true );

		$list_all_seat_ticket = [];
		if ( !empty($list_ticket_event) && is_array($list_ticket_event) ) {
			foreach ( $list_ticket_event as $ticket ) {
				if ( $ticket['ticket_id'] == $id_ticket ) {
					if (empty($ticket['seat_list'])) {
						$list_all_seat_ticket = [];
					} else {
						$list_all_seat_ticket = explode(',', $ticket['seat_list']);
					}
					
					$list_all_seat_ticket = array_map('trim', $list_all_seat_ticket);
				}
			}
		}

		$list_seat_rest = array_diff($list_all_seat_ticket, $list_seat_booked_by_ticket);

		return $list_seat_rest;
	}

	public function get_list_seat_map_rest( $id_event = null, $id_cal = null ) {
		if ( $id_event == null || $id_cal == null ) return [];

		// Get list seat booked in event and in one day (id_cal)
		$list_seat_booked = $this->get_list_seat_map_booked( $id_event, $id_cal );

		// Get list all seat by id event and id ticket
		$list_seat_map = get_post_meta( $id_event, OVA_METABOX_EVENT . 'ticket_map', true );

		$list_all_seat_map = [];

		if ( ! empty( $list_seat_map ) && is_array( $list_seat_map ) ) {
			// Seat
			if ( ! empty( $list_seat_map['seat'] ) && ! empty( $list_seat_map['seat'] ) && is_array( $list_seat_map['seat'] ) ) {
				foreach ( $list_seat_map['seat'] as $seat ) {
					if ( strpos( $seat['id'], ',' ) ) {
						foreach ( explode(",", $seat['id'] ) as $v ) {
							if ( $v != '' ) 
								$list_all_seat_map[] = trim($v);
						}
					} else {
						$list_all_seat_map[] = $seat['id'];
					}
				}
			}

			// Area
			if ( ! empty( $list_seat_map['area'] ) && ! empty( $list_seat_map['area'] ) && is_array( $list_seat_map['area'] ) ) {
				foreach ( $list_seat_map['area'] as $area ) {
					if ( strpos( $area['id'], ',' ) ) {
						foreach ( explode(",", $area['id'] ) as $v ) {
							if ( $v != '' ) 
								$list_all_seat_map[] = trim($v);
						}
					} else {
						$list_all_seat_map[] = $area['id'];
					}
				}
			}
		}

		$list_seat_rest = array_diff( $list_all_seat_map, $list_seat_booked );

		return $list_seat_rest;
	}
	
	public function check_seat_map_in_cart( $seat = '', $id_event = null, $id_cal = null ) {
		if ( $seat == '' || $id_event == null || $id_cal == null ) return false;

		$list_seat_rest = $this->get_list_seat_map_rest( $id_event, $id_cal );

		// check value seat exists
		if ( ! in_array( $seat, $list_seat_rest ) ) {
			return false;
		}

		return true;
	}
	
	public function check_seat_in_cart ($seat = [], $id_event = null, $id_cal = null, $id_ticket = null) {
		$seat_option = get_seat_option( $id_event );
		if ( $seat == [] || $id_event == null || $id_cal == null || $id_ticket == null || $seat_option != 'simple' ) return false;
		//check value seat duplicate
		if ( count(array_unique($seat)) < count($seat) ) {
			return false;
		}

		$list_seat_rest = $this->get_list_seat_rest($id_event, $id_cal, $id_ticket);

		// check value seat exists
		foreach ($seat as $value) {
			if (!in_array($value, $list_seat_rest)) {
				return false;
			}
		}
		return true;
	}

	public function booking_success( $booking_id, $payment_method, $orderid_woo = null ){

		$result = true;

		// Update Status in booking
		if ( apply_filters( 'el_new_order_update_status_completed', true ) ) {
			update_post_meta( $booking_id, OVA_METABOX_EVENT.'status', 'Completed', 'Pending' );
		}

		// Restrict when Reload page while ajax are processing checkout
		if ( ! isset( $_SESSION['booking_id_current'] ) || $_SESSION['booking_id_current'] != $booking_id ) {
	     	// Add Ticket
			$record_ticket_ids = EL_Ticket::instance()->add_ticket( $booking_id );

	     	// Update Record ticket ids to Booking 
			update_post_meta( $booking_id, OVA_METABOX_EVENT.'record_ticket_ids', $record_ticket_ids, '' );

			$update_cart = $this->booking_update_cart( $booking_id );

	     	// Update Payment Method to Booking Table
			if ( $payment_method == 'woo' ) {
				update_post_meta( $booking_id, OVA_METABOX_EVENT.'payment_method', esc_html__( 'Woo', 'eventlist' ).' - <a target="_blank" href="'.home_url('/').'wp-admin/post.php?post='.$orderid_woo.'&action=edit">'.$orderid_woo.'</a>' );
				update_post_meta( $booking_id, OVA_METABOX_EVENT.'orderid', $orderid_woo );

				if ( EL()->options->mail->get( 'enable_send_booking_email', 'yes' ) == 'yes' && apply_filters( 'el_new_order_use_system_mail', true ) ) {
					$result = el_sendmail_by_booking_id( $booking_id );
				}
			} else {
	        	// Send Mail
				if ( EL()->options->mail->get( 'enable_send_booking_email', 'yes' ) == 'yes' ) {
					$result = el_sendmail_by_booking_id( $booking_id );
				}

				update_post_meta( $booking_id, OVA_METABOX_EVENT.'payment_method', $payment_method );
			}

			$_SESSION['booking_id_current'] = $booking_id;

			$this->el_remove_holding_ticket( $booking_id );

			EL()->cart_session->remove();
		}

		return $result;
	}

	public function booking_update_cart( $booking_id ) {
		$cart = [];

		if ( ! $booking_id ) return false;

		$cart 			= get_post_meta( $booking_id, OVA_METABOX_EVENT . 'cart', true );
		$event_id 		= get_post_meta( $booking_id, OVA_METABOX_EVENT . 'id_event', true );
		$seat_option 	= get_post_meta( $event_id, OVA_METABOX_EVENT . 'seat_option', true );

		if ( ! $seat_option ) $seat_option = 'none';

		if ( $seat_option === 'simple' ) {
			$seats 		= [];
			$ticket_ids = get_post_meta( $booking_id, OVA_METABOX_EVENT . 'record_ticket_ids', true );

			if ( ! empty( $ticket_ids ) && is_array( $ticket_ids ) ) {
				foreach ( $ticket_ids as $ticket_id ) {
					$ticket_id_event = get_post_meta( $ticket_id, OVA_METABOX_EVENT . 'ticket_id_event', true );
					$seat = get_post_meta( $ticket_id, OVA_METABOX_EVENT . 'seat', true );

					if ( isset( $seats[$ticket_id_event] ) && is_array( $seats[$ticket_id_event] ) ) {
						$seats[$ticket_id_event][] = $seat;
					} else {
						$seats[$ticket_id_event] = [];
						$seats[$ticket_id_event][] = $seat;
					}
				}
			}

			if ( ! empty( $cart ) && is_array( $cart ) ) {
				foreach ( $cart as $k => $item ) {
					if ( isset( $item['seat'] ) && empty( $item['seat'] ) ) {
						$id = isset( $item['id'] ) ? $item['id'] : '';

						if ( $id && isset( $seats[$id] ) ) {
							$cart[$k]['seat'] = $seats[$id];
						}
					}
				}
			}
		}

		$update_cart = update_post_meta( $booking_id, OVA_METABOX_EVENT . 'cart', $cart );

		return $update_cart;
	}

	public function booking_hold( $booking_id ){
		$result = true;
		$result = el_sendmail_by_booking_id( $booking_id, 'hold' );
		$this->el_remove_holding_ticket( $booking_id );
		return $result;
	}

	public function get_list_booking_complete_by_id_event ( $id_event = null ) {
		if ($id_event == null) return;
		$agrs = [
			'post_type' => 'el_bookings',
			'post_status' => 'publish',
			"meta_query" => [
				'relation' => 'AND',
				[
					"key" => OVA_METABOX_EVENT . 'id_event',
					"value" => $id_event,
				],
				[
					'key' => OVA_METABOX_EVENT . 'status',
					'value' => 'Completed',
				]
			],
			'posts_per_page' => -1, 
			'numberposts' => -1,
			'nopaging' => true,
		];

		return get_posts( $agrs );
	}

	public function get_number_booking_id_event ( $id_event = null ) {
		if ($id_event == null) return;
		$agrs = [
			'post_type' => 'el_bookings',
			'post_status' => 'publish',
			"meta_query" => [
				'relation' => 'AND',
				[
					"key" => OVA_METABOX_EVENT . 'id_event',
					"value" => $id_event,
				],
				[
					'key' => OVA_METABOX_EVENT . 'status',
					'value' => 'Completed',
				]
			],
			'posts_per_page' => -1, 
			'numberposts' => -1,
			'nopaging' => true,
		];

		return count(get_posts( $agrs ));
	}

	public function get_list_booking_user_current ($paged=1) {
		$user_current = get_current_user_id();
		if (empty($user_current)) return [];

		$agrs = [
			'post_type' => 'el_bookings',
			'post_status' => 'publish',
			'posts_per_page' => apply_filters( 'el_posts_p_page_my_bookings', 9 ),
			'meta_query' => [
				'relation' => 'AND',
				[
					'key' => OVA_METABOX_EVENT . 'id_customer',
					'value' => $user_current
				],
				[
					'key' => OVA_METABOX_EVENT . 'status',
					'value' => apply_filters( 'get_list_booking_user_current_status', array( 'Completed', 'Canceled' ) ),
					'compare' => 'IN'
				]
			],
			"paged" => $paged,
		];

		return new WP_Query( $agrs );
	}

	// Get All Bookings Completed
	public function get_list_bookings ( $user_id ) {
		$agrs = [
			'post_type' => 'el_bookings',
			'post_status' => 'publish',
			'author'         => $user_id,
			'meta_query' => [
				'relation' => 'AND',
				
				[
					'key' => OVA_METABOX_EVENT . 'status',
					'value' => 'Completed',
				],
				[
					'key' => OVA_METABOX_EVENT . 'profit_status',
					'value' => array( '', 'Waiting','Completed' ),
					'compare' => 'IN',

				],
				
			],
			'posts_per_page' => -1, 
			'numberposts' => -1,
			'fields' => 'ids',
			'nopaging' => true,
		];

		return new WP_Query( $agrs );
	}

	// Get bookings with profit status waiting
	public function get_list_bookings_profit_wating( $user_id ){
		$agrs = [
			'post_type' => 'el_bookings',
			'post_status' => 'publish',
			'author'         => $user_id,
			'meta_query' => [
				'relation' => 'AND',
				
				[
					'key' => OVA_METABOX_EVENT . 'status',
					'value' => 'Completed',
				],
				[
					'key' => OVA_METABOX_EVENT . 'profit_status',
					'value' => 'Waiting',
				],
				
			],
			'posts_per_page' => -1, 
			'numberposts' => -1,
			'nopaging' => true,
		];

		return new WP_Query( $agrs );
	}

	// Get all bookings doesn't payout yet
	public function get_bookings_do_not_payout( $user_id ) {
		$agrs = [
			'post_type' => 'el_bookings',
			'post_status' => 'publish',
			'author'         => $user_id,
			'meta_query' => [
				'relation' => 'AND',
				
				[
					'key' => OVA_METABOX_EVENT . 'status',
					'value' => 'Completed',
				],
				[
					'key' => OVA_METABOX_EVENT . 'profit_status',
					'value' => '',
				],
				
			],
			'posts_per_page' => -1, 
			'numberposts' => -1,
			'nopaging' => true,
		];

		return new WP_Query( $agrs );
	}

	// Get Profit of Vendor in per booking - User for old version < 1.3.7
	public function get_profit_by_id_booking ($id_booking = null) {
		if ( $id_booking == null ) return ;

		$id_event 			= get_post_meta( $id_booking, OVA_METABOX_EVENT . 'id_event', true );
		$total_after_tax 	= get_post_meta( $id_booking, OVA_METABOX_EVENT . 'total_after_tax', true );
		$total_before_tax 	= get_post_meta( $id_booking, OVA_METABOX_EVENT .'total', true );
		$system_fee 		= get_post_meta( $id_booking, OVA_METABOX_EVENT .'system_fee', true );

		if ( floatval( $system_fee ) ) {
			$total_after_tax 	= floatval( $total_after_tax ) - floatval( $system_fee );
			$total_before_tax 	= floatval( $total_before_tax ) - floatval( $system_fee );
		}

		$list_ticket_by_id_booking = EL_Ticket::instance()->get_list_ticket_by_id_booking($id_booking);
		$number_ticket = count( $list_ticket_by_id_booking );

		$number_ticket_free = EL_Ticket::instance()->get_number_ticket_free_by_id_booking($id_booking);
		$number_ticket_paid = $number_ticket - $number_ticket_free; 

        $commission = get_commission_admin( $id_event, $total_before_tax, $number_ticket_paid, $number_ticket_free );

        $profit_included_tax = EL()->options->tax_fee->get('profit_included_tax');

		if ( $profit_included_tax == 'yes' ) {
			$profit = floatval( $total_after_tax ) - $commission;	
		} else {
			$profit = floatval( $total_before_tax ) - $commission;
		}
		
	    return apply_filters('el_get_profit_id_booking', $profit, $id_booking);
	}

	// Get Commission of Admin in per booking  - User for old version < 1.3.7
	public function get_commission_by_id_booking ($id_booking = null) {
		if ( $id_booking == null ) return ;

		$id_event 					= get_post_meta( $id_booking, OVA_METABOX_EVENT . 'id_event', true );
		$total_before_tax 			= get_post_meta( $id_booking, OVA_METABOX_EVENT .'total', true );
		$system_fee 				= get_post_meta( $id_booking, OVA_METABOX_EVENT .'system_fee', true );
		$list_ticket_by_id_booking 	= EL_Ticket::instance()->get_list_ticket_by_id_booking( $id_booking );
		$number_ticket 				= count( $list_ticket_by_id_booking );
		$number_ticket_free 		= EL_Ticket::instance()->get_number_ticket_free_by_id_booking( $id_booking );
		$number_ticket_paid 		= $number_ticket - $number_ticket_free;

		if ( floatval( $system_fee ) ) {
			$total_before_tax = floatval( $total_before_tax ) - floatval( $system_fee );
		}

        $commission = get_commission_admin( $id_event, $total_before_tax, $number_ticket_paid, $number_ticket_free );

        if ( floatval( $system_fee ) ) {
			$commission = floatval( $commission ) + floatval( $system_fee );
		}

        return apply_filters('el_get_commission_id_booking', $commission, $id_booking);
	}

	// Get Tax in per booking
	public function get_tax_by_id_booking( $id_booking = null ) {
		$total_after_tax 	= get_post_meta( $id_booking, OVA_METABOX_EVENT .'total_after_tax', true );
		$total_before_tax 	= get_post_meta( $id_booking, OVA_METABOX_EVENT .'total', true );

		$tax = floatval( $total_after_tax ) - floatval( $total_before_tax );

		return $tax;
	}

	/**
	 * Create post type: holding_ticket
	 */
	public function el_create_holding_ticket( $post_data, $booking_id ) {
		if ( empty( $post_data ) ) $post_data = $_POST['data'];
		$cart 			= isset( $post_data['cart'] ) ? $post_data['cart'] : [];
		$id_event 		= isset( $post_data['ide'] ) ? sanitize_text_field( $post_data['ide'] ) : null;
		$id_cal 		= isset( $post_data['idcal'] ) ? sanitize_text_field( $post_data['idcal'] ) : null;
		$payment_method = isset( $post_data['payment_method'] ) ? sanitize_text_field( $post_data['payment_method'] ) : '';
		$seat_option 	= isset( $post_data['seat_option'] ) ? sanitize_text_field( $post_data['seat_option'] ) : '';
		$extra_service 	= get_post_meta( $booking_id, OVA_METABOX_EVENT.'extra_service', true );
		$current_time 	= current_time( 'timestamp' );
		$seats 			= [];

		$event_obj 			= get_post( $id_event );
		$event_author_id 	= $event_obj->post_author;


		if ( $seat_option === 'map' ) {
			$seats = el_get_ticket_ids_form_cart( $cart, 'map' );
		}

		if ( $seat_option === 'simple' ) {
			$seats = $this->el_get_seats_from_cart( $post_data );
		}

		if ( ! empty( $seats ) && is_array( $seats ) ) {
			foreach ( $seats as $seat ) {
				$code = $id_event . '_' . $id_cal . '_' . $seat;
				$data = array(
					'post_type' 	=> 'holding_ticket',
					'post_title' 	=> $code,
					'post_status' 	=> 'publish',
					'post_name' 	=> $code,
					'post_author' 	=> $event_author_id,
				);
				$data_extra_service = isset( $extra_service[$seat] ) ? $extra_service[$seat] : '';

				$meta_input = array(
					$this->_prefix.'id_event' 		=> $id_event,
					$this->_prefix.'id_cal' 		=> $id_cal,
					$this->_prefix.'seat' 			=> $seat,
					$this->_prefix.'booking_id' 	=> $booking_id,
					$this->_prefix.'payment_method' => $payment_method,
					$this->_prefix.'seat_option' 	=> $seat_option,
					$this->_prefix.'cart' 			=> $cart,
					$this->_prefix.'current_time' 	=> $current_time,
					$this->_prefix.'code_checkout' 	=> $code,
					$this->_prefix.'extra_service'  => $data_extra_service,
				);

				$data['meta_input'] = apply_filters( 'el_create_holding_ticket_metabox_input', $meta_input );
				$holding_ticket_id 	= wp_insert_post( $data, true );
			}
		}

		if ( $seat_option === 'none' ) {
			if ( ! empty( $cart ) && is_array( $cart ) ) {
				foreach( $cart as $ticket ) {
					$ticket_id 	= isset( $ticket['id'] ) && $ticket['id'] ? $ticket['id'] : '';
					$qty 		= isset( $ticket['qty'] ) && $ticket['qty'] ? absint( $ticket['qty'] ) : 0;

					if ( $ticket_id ) {
						$code = $id_event . '_' . $id_cal . '_' . $ticket_id;
						$data = array(
							'post_type' 	=> 'holding_ticket',
							'post_title' 	=> $code,
							'post_status' 	=> 'publish',
							'post_name' 	=> $code,
							'post_author' 	=> $event_author_id,
						);

						$data_extra_service = isset( $extra_service[$ticket_id] ) ? $extra_service[$ticket_id] : '';

						$meta_input = array(
							$this->_prefix.'ticket_id' 		=> $ticket_id,
							$this->_prefix.'id_event' 		=> $id_event,
							$this->_prefix.'id_cal' 		=> $id_cal,
							$this->_prefix.'qty' 			=> $qty,
							$this->_prefix.'booking_id' 	=> $booking_id,
							$this->_prefix.'payment_method' => $payment_method,
							$this->_prefix.'seat_option' 	=> $seat_option,
							$this->_prefix.'cart' 			=> $cart,
							$this->_prefix.'current_time' 	=> $current_time,
							$this->_prefix.'code_checkout' 	=> $code,
							$this->_prefix.'extra_service' 	=> $data_extra_service,
						);

						$data['meta_input'] = apply_filters( 'el_create_holding_ticket_metabox_input', $meta_input );
						$holding_ticket_id = wp_insert_post( $data, true );
					}
				}
			}
		}

	}

	public function el_remove_holding_ticket( $booking_id ){
		
		update_post_meta( $booking_id, OVA_METABOX_EVENT.'status_holding_ticket', 'Completed' );
		// Remove holding ticket when Complete payment
		$agrs = [
			'post_type' 		=> 'holding_ticket',
			'post_status' 		=> 'publish',
			'posts_per_page' 	=> -1,
			'fields' 			=> 'ids',
			'meta_query' 		=> array(
				array(
					'key' 		=> OVA_METABOX_EVENT.'booking_id',
					'value' 	=> $booking_id,
					'compare' 	=> '='	
				),
			),
		];

		$holding_tickets = get_posts( $agrs );

		if ( ! empty( $holding_tickets ) && is_array( $holding_tickets ) ) {
			foreach ( $holding_tickets as $ht_id ) {
				wp_delete_post( $ht_id );
			}
		}
	}

	/**
	 * Check: holding_ticket
	 */
	public function el_check_holding_ticket( $post_data ) {
		if ( empty( $post_data ) ) $post_data = $_POST['data'];

		$result = array();
		$time_countdown_checkout = intval( EL()->options->checkout->get('max_time_complete_checkout', 600) );
		$cart 			= isset( $post_data['cart'] ) ? $post_data['cart'] : [];
		$id_event 		= isset( $post_data['ide'] ) ? sanitize_text_field( $post_data['ide'] ) : null;
		$id_cal 		= isset( $post_data['idcal'] ) ? sanitize_text_field( $post_data['idcal'] ) : null;
		$payment_method = isset( $post_data['payment_method'] ) ? sanitize_text_field( $post_data['payment_method'] ) : '';
		$seat_option 	= isset( $post_data['seat_option'] ) ? sanitize_text_field( $post_data['seat_option'] ) : '';
		$seats 			= [];
		$area_available = $area_in_cart = [];

		$event_obj 			= get_post( $id_event );
		$event_author_id 	= $event_obj->post_author;


		if ( $seat_option === 'map' ) {
			$seats 			= el_get_ticket_ids_form_cart( $cart, 'map' );
			$area_available = $this->el_get_area_qty_available( $id_event, $id_cal );

			foreach ( $cart as $cart_item ) {
				if ( isset( $cart_item['qty'] ) && absint( $cart_item['qty'] ) ) {
					$area_in_cart[$cart_item['id']] = absint( $cart_item['qty'] );
				}
			}
		}

		if ( $seat_option === 'simple' ) {
			$seats = $this->el_get_seats_from_cart( $post_data );
		}

		if ( ! empty( $seats ) && is_array( $seats ) ) {
			$code_arr = array();

			foreach ( $seats as $seat ) {
				$code = $id_event . '_' . $id_cal . '_' . $seat;
				array_push( $code_arr, $code );
			}

			$seats_regexp = implode( '|', $code_arr );

			$agrs = [
				'post_type' 		=> 'holding_ticket',
				'post_status' 		=> 'publish',
				'posts_per_page' 	=> -1,
				'fields' 			=> 'ids',
				'meta_query' 		=> array(
					array(
						'key' 		=> $this->_prefix.'code_checkout',
						'value' 	=> $seats_regexp,
						'compare' 	=> 'REGEXP'	
					),
				),
			];

			$holding_tickets = get_posts( $agrs );

			if ( ! empty( $holding_tickets ) && is_array( $holding_tickets ) ) {
				$current_time = current_time( 'timestamp' );

				foreach ( $holding_tickets as $ht_id ) {
					$time_sumbit_checkout 	= get_post_meta( $ht_id, $this->_prefix.'current_time', true );
					$seat_id 				= get_post_meta( $ht_id, $this->_prefix.'seat', true );
					$past_time 				= absint( $current_time ) - absint( $time_sumbit_checkout );

					if ( $past_time < $time_countdown_checkout ) {
						if ( array_key_exists( $seat_id, $area_available ) ) {
							$area_qty_available = absint( $area_available[$seat_id] );
							$area_qty_in_cart 	= isset( $area_in_cart[$seat_id] ) ? absint( $area_in_cart[$seat_id] ) : 0;

							// Get area qty holding
							$area_holding 	= [];
							$ht_cart 		= get_post_meta( $ht_id, $this->_prefix.'cart', true );

							if ( ! empty( $ht_cart ) && is_array( $ht_cart ) ) {
								foreach ( $ht_cart as $ht_cart_item ) {
									if ( isset( $ht_cart_item['qty'] ) && absint( $ht_cart_item['qty'] ) ) {
										$area_holding[$ht_cart_item['id']] = absint( $ht_cart_item['qty'] );
									}
								}
							}
							$area_qty_holding = isset( $area_holding[$seat_id] ) ? absint( $area_holding[$seat_id] ) : 0;

							$check_area_qty = $area_qty_available - $area_qty_holding;

							if ( $area_qty_in_cart > $check_area_qty ) {
								if ( WC()->cart ) {
									WC()->cart->empty_cart();
								}

								$result['el_option'] = 'holding_ticket';

								if ( $check_area_qty > 0 ) {
									$result['el_message'] = sprintf( esc_html__( "Maximum %s: %s","eventlist" ), $seat_id, $check_area_qty );
								} else {
									$result['el_message'] = sprintf( esc_html__( "%s is out of stock","eventlist" ), $seat_id );
								}
								
								$result['el_reload_page'] = esc_html__( "Click here to reload the page or the page will automatically reload after 10 seconds.","eventlist" );
								break;
							}
						} else {
							if ( WC()->cart ) {
								WC()->cart->empty_cart();
							}

							if ( ! empty( $seats ) && is_array( $seats ) ) $seats = array_unique( $seats );

							$result['el_option'] 		= 'holding_ticket';
							$result['el_message'] 		= sprintf( esc_html__( "Seats %s have been booked","eventlist" ), esc_html( implode(", ", $seats ) ) );
							$result['el_reload_page'] 	= esc_html__( "Click here to reload the page or the page will automatically reload after 10 seconds.","eventlist" );
							break;
						}
					}
				}
			}
		}

		if ( $seat_option === 'none' ) {
			$code_checkout = $this->el_get_code_checkout_from_cart( $post_data );

			if ( !empty( $code_checkout ) && is_array( $code_checkout ) ) {
				$code_checkout_regexp = implode( '|', $code_checkout );

				$agrs = [
					'post_type' 		=> 'holding_ticket',
					'post_status' 		=> 'publish',
					'posts_per_page' 	=> -1,
					'fields' 			=> 'ids',
					'meta_query' 		=> array(
						array(
							'key' 		=> $this->_prefix.'code_checkout',
							'value' 	=> $code_checkout_regexp,
							'compare' 	=> 'REGEXP'	
						),
					),
				];

				$holding_tickets = get_posts( $agrs );

				if ( ! empty( $holding_tickets ) && is_array( $holding_tickets ) ) {
					$data_ticket = [];

					foreach( $cart as $ticket ) {
						$ticket_id 	= isset( $ticket['id'] ) && $ticket['id'] ? $ticket['id'] : '';
						$qty 		= isset( $ticket['qty'] ) && $ticket['qty'] ? absint( $ticket['qty'] ) : 0;
						$name 		= isset( $ticket['name'] ) && $ticket['name'] ? $ticket['name'] : '';

						if ( $ticket_id ) {
							$data_ticket[$ticket_id] = array(
								'name' 	=> $name,
								'qty' 	=> $qty
							);
						}
					}

					$current_time = current_time( 'timestamp' );

					foreach ( $holding_tickets as $ht_id ) {
						$ticket_id 				= get_post_meta( $ht_id, $this->_prefix.'ticket_id', true );
						$total_ticket 			= $this->get_number_ticket_rest( $id_event, $id_cal, $ticket_id );
						$time_sumbit_checkout 	= get_post_meta( $ht_id, $this->_prefix.'current_time', true );
						$ht_qty 				= get_post_meta( $ht_id, $this->_prefix.'qty', true );
						$past_time 				= absint( $current_time ) - absint( $time_sumbit_checkout );

						$qty_in_cart 	= isset( $data_ticket[$ticket_id] ) ? absint( $data_ticket[$ticket_id]['qty'] ) : 0;
						$name_in_cart 	= isset( $data_ticket[$ticket_id] ) ? $data_ticket[$ticket_id]['name'] : '';

						$check_qty = absint( $total_ticket ) - absint( $ht_qty );

						if ( $past_time < $time_countdown_checkout && $qty_in_cart > $check_qty ) {
							if ( WC()->cart ) {
								WC()->cart->empty_cart();
							}

							$result['el_option'] = 'holding_ticket';
							$result['el_message'] = sprintf( esc_html__( "Ticket %s is out of stock!","eventlist" ), esc_html( $name_in_cart ) );
							$result['el_reload_page'] = esc_html__( "Click here to reload the page or the page will automatically reload after 10 seconds.","eventlist" );
							break;
						}
					}
				}
			}
		}


		return $result;
	}

	/**
	 * Get seats from cart
	 */
	public function el_get_seats_from_cart( $post_data ) {
		$id_event	= isset($post_data['ide']) ? sanitize_text_field( $post_data['ide'] ) : null;
		$id_cal 	= isset($post_data['idcal']) ? sanitize_text_field( $post_data['idcal'] ) : null;
		$cart 		= isset($post_data['cart']) ? $post_data['cart'] : [];

		$list_type_ticket = get_post_meta( $id_event, OVA_METABOX_EVENT . 'ticket', true);
		$list_choose_seat = [];

		if ( !empty( $list_type_ticket ) && is_array( $list_type_ticket ) ) {
			foreach( $list_type_ticket as $ticket ) {
				$list_choose_seat[$ticket['ticket_id']] = $ticket['setup_seat'];
			}
		}

		// get list id_ticket in cart
		$seats = [];

		if ( !empty( $cart ) && is_array( $cart ) ) {
			foreach( $cart as $item ) {
				if ( $list_choose_seat[$item['id']] == 'no' ) {
					$seat_booking = $this->auto_book_seat_of_ticket( $id_event, $id_cal, $item['id'], $item['qty'] );

					if ( !empty( $seat_booking ) && is_array( $seat_booking ) ) {
						$seats = array_merge( $seats, $seat_booking );
					}
				} else {
					if ( !empty( $item['seat'] ) && is_array( $item['seat'] ) ) {
						$seats = array_merge( $seats, $item['seat'] );
					}
				}
			}
		}

		return $seats;
	}

	/**
	 * Get code checkout from cart
	 */
	public function el_get_code_checkout_from_cart( $post_data ) {
		$id_event	= isset( $post_data['ide'] ) ? sanitize_text_field( $post_data['ide'] ) : null;
		$id_cal 	= isset( $post_data['idcal'] ) ? sanitize_text_field( $post_data['idcal'] ) : null;
		$cart 		= isset( $post_data['cart'] ) ? $post_data['cart'] : [];

		$code_checkout = [];

		if ( ! empty( $cart ) && is_array( $cart ) ) {
			foreach( $cart as $ticket ) {
				$ticket_id 	= isset( $ticket['id'] ) && $ticket['id'] ? $ticket['id'] : '';

				if ( $ticket_id ) {
					$code = $id_event . '_' . $id_cal . '_' . $ticket_id;
					array_push( $code_checkout, $code );
				}
			}
		}

		return $code_checkout;
	}

	// Make PDF Invoices
	public function el_make_pdf_invoice_by_booking_id( $booking_id = null ) {
		if ( ! $booking_id ) return false;

		$pdf = new EL_PDF();

		$pdf_url = $pdf->make_pdf_invoice( $booking_id );

		return $pdf_url;
	}
	
	// Get data seat map status (past, upcoming)
	public function get_data_seat_map_status( $event_id = null, $seat_booked = [] ) {
		if ( ! $event_id ) return false;

		// Date & Time of Ticket
		$ticket_startdate = $ticket_starttime = $ticket_closedate = $ticket_closetime = '';

		$data_seat = array(
			'type_seat' => array(),
			'past' 		=> array(),
			'upcoming' 	=> array()
		);

		$ticket_map = get_post_meta( $event_id, OVA_METABOX_EVENT . 'ticket_map', true );

		if ( ! empty( $ticket_map ) && is_array( $ticket_map ) ) {
			$ticket_startdate = isset( $ticket_map['start_ticket_date'] ) ? $ticket_map['start_ticket_date'] : '';
			$ticket_starttime = isset( $ticket_map['start_ticket_time'] ) ? $ticket_map['start_ticket_time'] : '';
			$ticket_closedate = isset( $ticket_map['close_ticket_date'] ) ? $ticket_map['close_ticket_date'] : '';
			$ticket_closetime = isset( $ticket_map['close_ticket_time'] ) ? $ticket_map['close_ticket_time'] : '';
		}

		// Get current time
		$current_time = el_get_current_time_by_event( $event_id );

		// Check seat date,time
		if ( isset( $ticket_map['seat'] ) && ! empty( $ticket_map['seat'] ) && is_array( $ticket_map['seat'] ) ) {
			foreach ( $ticket_map['seat'] as $k => $item_seat ) {
				$start_date = isset( $item_seat['start_date'] ) && $item_seat['start_date'] ? $item_seat['start_date'] : '';
				$start_time = isset( $item_seat['start_time'] ) && $item_seat['start_time'] ? $item_seat['start_time'] : '';
				$end_date 	= isset( $item_seat['end_date'] ) && $item_seat['end_date'] ? $item_seat['end_date'] : '';
				$end_time 	= isset( $item_seat['end_time'] ) && $item_seat['end_time'] ? $item_seat['end_time'] : '';

				if ( ! $start_date && ! $end_date ) {
					$start_date = $ticket_startdate;
					$start_time = $ticket_starttime;
					$end_date 	= $ticket_closedate;
					$end_time 	= $ticket_closetime;
				}

				if ( $start_date && $end_date ) {
					$start 	= strtotime( $start_date . ' ' . $start_time );
					$end 	= strtotime( $end_date . ' ' . $end_time );

					if ( $current_time < $end && $current_time >= $start ) {
						if ( isset( $item_seat['type_seat'] ) && $item_seat['type_seat'] ) {
							$data_seat['type_seat'][$item_seat['type_seat']] = esc_html__( 'Selling', 'eventlist' );
						}
					} elseif ( $current_time > $end ) {
						$seat_ids = isset( $item_seat['id'] ) ? explode( ',', $item_seat['id'] ) : '';

						if ( ! empty( $seat_ids ) && is_array( $seat_ids ) ) {
							$seat_ids 			= array_map( 'trim', $seat_ids );
							$data_seat['past'] 	= array_unique( array_merge( $data_seat['past'], $seat_ids ) );
						}

						if ( isset( $item_seat['type_seat'] ) && $item_seat['type_seat'] ) {
							$data_seat['type_seat'][$item_seat['type_seat']] = esc_html__( 'Closed', 'eventlist' );
						}
					} elseif ( $current_time < $start ) {
						$seat_ids = isset( $item_seat['id'] ) ? explode( ',', $item_seat['id'] ) : '';

						if ( ! empty( $seat_ids ) && is_array( $seat_ids ) ) {
							$seat_ids 				= array_map( 'trim', $seat_ids );
							$data_seat['upcoming'] 	= array_unique( array_merge( $data_seat['upcoming'], $seat_ids ) );
						}

						if ( isset( $item_seat['type_seat'] ) && $item_seat['type_seat'] ) {
							$data_seat['type_seat'][$item_seat['type_seat']] = esc_html__( 'Upcoming', 'eventlist' );
						}
					} else { /*nothing*/ }
				}
			}
		}

		// Check area date,time
		if ( isset( $ticket_map['area'] ) && ! empty( $ticket_map['area'] ) && is_array( $ticket_map['area'] ) ) {
			foreach ( $ticket_map['area'] as $k => $item_area ) {
				$start_date = isset( $item_area['start_date'] ) && $item_area['start_date'] ? $item_area['start_date'] : '';
				$start_time = isset( $item_area['start_time'] ) && $item_area['start_time'] ? $item_area['start_time'] : '';
				$end_date 	= isset( $item_area['end_date'] ) && $item_area['end_date'] ? $item_area['end_date'] : '';
				$end_time 	= isset( $item_area['end_time'] ) && $item_area['end_time'] ? $item_area['end_time'] : '';

				if ( ! $start_date && ! $end_date ) {
					$start_date = $ticket_startdate;
					$start_time = $ticket_starttime;
					$end_date 	= $ticket_closedate;
					$end_time 	= $ticket_closetime;
				}

				if ( $start_date && $end_date ) {
					$start 	= strtotime( $start_date . ' ' . $start_time );
					$end 	= strtotime( $end_date . ' ' . $end_time );

					if ( $current_time < $end && $current_time >= $start ) {
						if ( isset( $item_area['type_seat'] ) && $item_area['type_seat'] ) {
							$data_seat['type_seat'][$item_area['type_seat']] = esc_html__( 'Selling', 'eventlist' );
						}
					} elseif ( $current_time > $end ) {
						$seat_ids = isset( $item_area['id'] ) ? explode( ',', $item_area['id'] ) : '';

						if ( ! empty( $seat_ids ) && is_array( $seat_ids ) ) {
							$seat_ids 			= array_map( 'trim', $seat_ids );
							$data_seat['past'] 	= array_unique( array_merge( $data_seat['past'], $seat_ids ) );
						}

						if ( isset( $item_area['type_seat'] ) && $item_area['type_seat'] ) {
							$data_seat['type_seat'][$item_area['type_seat']] = esc_html__( 'Closed', 'eventlist' );
						}
					} elseif ( $current_time < $start ) {
						$seat_ids = isset( $item_area['id'] ) ? explode( ',', $item_area['id'] ) : '';

						if ( ! empty( $seat_ids ) && is_array( $seat_ids ) ) {
							$seat_ids 				= array_map( 'trim', $seat_ids );
							$data_seat['upcoming'] 	= array_unique( array_merge( $data_seat['upcoming'], $seat_ids ) );
						}

						if ( isset( $item_area['type_seat'] ) && $item_area['type_seat'] ) {
							$data_seat['type_seat'][$item_area['type_seat']] = esc_html__( 'Upcoming', 'eventlist' );
						}
					} else { /*nothing*/ }
				}
			}
		}

		return $data_seat;
	}

	// Get data area qty by event id
	public function el_get_area_qty( $event_id = null ) {
		if ( ! $event_id ) return [];

		$area_qty_available = [];

		$ticket_map = get_post_meta( $event_id, OVA_METABOX_EVENT . 'ticket_map', true );

		if ( isset( $ticket_map['area'] ) && ! empty( $ticket_map['area'] ) && is_array( $ticket_map['area'] ) ) {
			foreach ( $ticket_map['area'] as $item ) {
				$id 	= isset( $item['id'] ) ? $item['id'] : '';
				$qty 	= isset( $item['qty'] ) ? absint( $item['qty'] ) : '';

				if ( $id && $qty ) {
					if ( in_array( $id, $area_qty_available ) ) {
						$area_qty_available[$id] += $qty;
					} else {
						$area_qty_available[$id] = $qty;
					}
				}
			}
		}

		return $area_qty_available;
	}

	// Het data area qty available
	public function el_get_area_qty_available( $event_id = null, $cal_id = null ) {
		if ( $event_id == null || $cal_id == null ) return array();

		$area_qty 	= $this->el_get_area_qty( $event_id );
		$qty_booked = $this->el_get_area_qty_booked( $event_id, $cal_id );

		foreach ( $area_qty as $id => $qty ) {
			if ( isset( $qty_booked[$id] ) && absint( $qty_booked[$id] ) ) {
				$area_qty[$id] = absint( $qty ) - absint( $qty_booked[$id] );

				if ( $area_qty[$id] < 0 ) $area_qty[$id] = 0;
			}
		}

		return $area_qty;
	}

	public function el_get_area_qty_booked( $event_id = null, $cal_id = null ) {
		if ( $event_id == null || $cal_id == null ) return [];

		$area_qty_booked = [];
		$event_ids = el_get_product_ids_multi_lang( $event_id );

		$args = [
			'post_type' 		=> 'el_bookings',
			'post_status' 		=> 'publish',
			'fields'			=> 'ids',
			'posts_per_page' 	=> -1,
			'numberposts' 		=> -1,
			'nopaging' 			=> true,
			'meta_query' 		=> [
				'relation' 		=> 'AND',
				[
					'key' 		=> $this->_prefix . 'id_event',
					'value' 	=> $event_ids,
					'compare' 	=> 'IN',
				],
				[
					'key' 		=> $this->_prefix . 'id_cal',
					'value' 	=> $cal_id,
				],
				[
					'key' 		=> $this->_prefix . 'status',
					'value' 	=> 'Completed',
				]
			]
			
		];

		$bookings = get_posts( $args );

		if ( ! empty( $bookings ) && is_array( $bookings ) ) {
			foreach ( $bookings as $booking_id ) {
				$arr_area = get_post_meta( $booking_id, OVA_METABOX_EVENT . 'arr_area', true );
				$area_qty = get_post_meta( $booking_id, OVA_METABOX_EVENT . 'list_qty_ticket_by_id_ticket', true );

				if ( empty( $arr_area ) ) $arr_area = [];
				if ( empty( $area_qty ) ) $arr_area = [];

				if ( ! empty( $area_qty ) ) {
					foreach ( $area_qty as $id => $qty ) {
						if ( in_array( $id, $arr_area ) ) {
							if ( array_key_exists( $id, $area_qty_booked ) ) {
								$area_qty_booked[$id] += $qty;
							} else {
								$area_qty_booked[$id] = $qty;
							}
						}
					}
				}
			}
		}
		
		return $area_qty_booked;
	}

	function get_list_seat_holding_ticket( $id_event = null, $id_cal = null ){
		$result = array();
		$list_seat = array();
		if ( empty( $id_event ) || empty( $id_cal ) ) {
			return $result;
		}
		// Query
		$args = array(
			'post_type' => 'holding_ticket',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => $this->_prefix.'id_event',
					'value' => $id_event,
				),
				array(
					'key' => $this->_prefix.'id_cal',
					'value' => $id_cal,
				),
			),
		);

		$holding_ticket_ids = get_posts( $args );
		// Add seats to list_seat
		if ( count( $holding_ticket_ids ) > 0 ) {
			foreach ( $holding_ticket_ids as $ticket_id ) {
				$seat = get_post_meta( $ticket_id, $this->_prefix.'seat', true );
				$list_seat[] = $seat;
			}
		}
		$seat_option = get_post_meta( $id_event, $this->_prefix.'seat_option', true );
		
		// Get seat area
		if ( $seat_option == 'map' ) {
			$ticket_map = get_post_meta( $id_event, $this->_prefix.'ticket_map', true );
			$seat_area = isset( $ticket_map['area'] ) ? $ticket_map['area'] : array();
			$list_seat_area = array();
			if ( ! empty( $seat_area ) ) {
				foreach ( $seat_area as $key => $val ) {
					$list_seat_area[] = $val['id'];
				}
			}
		}

		// Remove seat area in list_seat

		if ( ! empty( $list_seat ) && ! empty( $list_seat_area ) ) {
			$result = array_diff( $list_seat, $list_seat_area );
		}

		return $result;
	}
}