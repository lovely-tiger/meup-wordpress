<?php
defined( 'ABSPATH' ) || exit;



/**
 * Admin Assets classes
 */
class EL_Event extends EL_Abstract_Event{
	
	protected static $_instance = array();

	/**
	 * Constructor
	 */
	public function __construct(){
	}


	static function el_setup_event_data( $post ) {
		unset( $GLOBALS['event'] );

		if ( is_int( $post ) )
			$post = get_post( $post );

		if ( !$post )
			$post = $GLOBALS['post'];

		if ( empty( $post->post_type ) || !in_array( $post->post_type, array( 'event' ) ) )
			return;

		return $GLOBALS['event'] = EL_Event::instance( $post );
	}


	static function instance( $event, $options = null ) {
		$post = $event;
		if ( $event instanceof WP_Post ) {
			$id = $event->ID;
		} elseif ( is_object( $event ) && isset( $event->ID ) ) {
			$id = $event->ID;
		} else {
			$id = $event;
		}

		if ( empty( self::$_instance[$id] ) ) {
			return self::$_instance[$id] = new self( $post, $options );
		} else {
			$event = self::$_instance[$id];
			return new self( $post, $options );
		}
		return self::$_instance[$id];
	}
	

	public function get_status_event () {
		
		$eid = get_the_ID();

		$time_start = get_post_meta( $eid, OVA_METABOX_EVENT . 'start_date_str', true  );
		$time_end = get_post_meta( $eid, OVA_METABOX_EVENT . 'end_date_str', true  );
		$cal = get_post_meta( $eid, OVA_METABOX_EVENT . 'option_calendar', true  );

		$time_start = !empty( $time_start ) ? (int) $time_start : 0;
		$time_end = !empty( $time_end ) ? (int) $time_end : 0;


		$current_time = current_time('timestamp');

		$status = "";
		if ( $time_start !== 0 &&  $time_end !== 0) {
			if ( $current_time < $time_end || (  $current_time < $time_start && $cal === 'auto' ) ) {
				$status = '<span class="status upcomming">'.esc_html__( 'Upcoming', 'eventlist' ).'</span>';
			} else if ( $current_time >= $time_start && $current_time <= $time_end ) {
				$status = '<span class="status opening">'.esc_html__( 'Opening', 'eventlist' ).'</span>';
			} else {
				$status = '<span class="status closed">'.esc_html__( 'Closed', 'eventlist' ).'</span>';
			}
		}
		return $status;
	}


	static public function get_event_date( $type_display = null ) {
		
		$eid = get_the_ID();

		$time_start = get_post_meta( $eid, OVA_METABOX_EVENT.'start_date_str', true) ;
		$time_end = get_post_meta( $eid, OVA_METABOX_EVENT.'end_date_str', true);
		
		$option_calendar = get_post_meta( $eid, OVA_METABOX_EVENT.'option_calendar', true);
		$calendar_recurrence = get_post_meta( $eid, OVA_METABOX_EVENT.'calendar_recurrence', true);
		$schedules_time = get_post_meta( $eid, OVA_METABOX_EVENT . 'schedules_time', true);

		if (empty($time_start) && empty($time_end)) return;

		$arr_start_date = $arr_end_date = array();
		$date_end_i18n = $date_end_i18n = $start_hour  = $end_hour = "";
		
		$display_date = EL()->options->event->get( 'display_date_opt' );
		
		if ($option_calendar == 'auto') {

			// Get future date in event recuring
			if ( $calendar_recurrence ) {
				foreach ( $calendar_recurrence as $value ) {
					if ( ( strtotime($value['date']) - strtotime('today') ) >= 0 ) {
						$arr_start_date[] = strtotime( $value['date'] .' '. $value['start_time'] );
					}
				}
			}

			// Get start date
			$time_start_current = ( $arr_start_date != array() ) ? min( $arr_start_date ) : $time_start ;
			$date_start_i18n = date_i18n( get_option( 'date_format' ), $time_start_current );
			$start_hour = date_i18n( get_option( 'time_format' ), $time_start_current );

			// Get end date
			$date_end_i18n = date_i18n( get_option( 'date_format' ), $time_end );
			$end_hour = date_i18n( get_option( 'time_format' ), $time_end );

			// Get Day
			$weekdays_start = date_i18n( 'D', $time_start_current );
			$weekdays_end = date_i18n( 'D', $time_end );

		} else { 

			// Get future date in event 
			$manual_calendars = get_post_meta( $eid, OVA_METABOX_EVENT.'calendar', true);
			if ( $manual_calendars ) {
				foreach ( $manual_calendars as $value ) {
					if ( ( strtotime($value['date']) - strtotime('today') ) >= 0 ) {
						$arr_start_date[] = strtotime( $value['date'] .' '. $value['start_time'] );
						$arr_end_date[] = strtotime( $value['date'] .' '. $value['end_time'] );
					}
					
				}
			}

			// Get start date
			$time_start_current = ( $arr_start_date != array() ) ? min( $arr_start_date ) : $time_start ;

			if ( $time_start_current !== 0 ) {
				
				$date_start_i18n = date_i18n( get_option( 'date_format' ), $time_start_current );
				$start_hour = date_i18n( get_option( 'time_format' ), $time_start_current );

			}
			

			if ( $time_end !== 0 ) {
				
				$date_end_i18n = date_i18n( get_option( 'date_format' ), $time_end );
				$end_hour = date_i18n( get_option( 'time_format' ), $time_end );

			}
			

			// Get Day
			$weekdays_start = $time_start_current ? date_i18n( 'D', $time_start_current ) : '';
			$weekdays_end = $time_end ? date_i18n( 'D', $time_end ) : '' ;
		}
		
		
		$html = "";
		switch ($type_display) {

			case 'full_format': {
				if ( $date_start_i18n == $date_end_i18n) {

					if( $weekdays_start ){
						$html .= '<span class="start-time">' . $weekdays_start . ', ' . $date_start_i18n . '</span>';	
					}else{
						$html .= '<span class="start-time">' . $date_start_i18n . '</span>';
					}
					

					if ( EL()->options->event->get('show_hours_single', 'yes') == 'yes' && is_singular('event') ) {
						if(!$schedules_time){
					
							$html .= '<span class="end-time"> (' . $start_hour . ' - ' . $end_hour . ')</span>';
						}
					}
					if ( EL()->options->event->get('show_hours_archive', 'yes') == 'yes' && !is_singular('event') ) {
						if(!$schedules_time){
					
							$html .= '<span class="end-time"> (' . $start_hour . ' - ' . $end_hour . ')</span>';
						}
						
					}

				} else {

					if( $weekdays_start ){
						$html .= '<span class="start-time">' . $weekdays_start . ', ' . $date_start_i18n . '</span>';
					}else{
						$html .= '<span class="start-time">' . $date_start_i18n . '</span>';
					}

					$html .= '<span class="separator"> - </span>';

					$html .= '<span class="end-time">' . $weekdays_end . ', ' . $date_end_i18n . '</span>';

					if ( EL()->options->event->get('show_hours_single', 'yes') == 'yes' && is_singular('event') ) {
						if(!$schedules_time){
	
							$html .= '<span class="time"> (' . $start_hour . ' - ' . $end_hour . ')</span>';
						}
					}
					if ( EL()->options->event->get('show_hours_archive', 'yes') == 'yes' && !is_singular('event') ) {
						if(!$schedules_time){
			
							$html .= '<span class="time"> (' . $start_hour . ' - ' . $end_hour . ')</span>';
						}
					}
				}
				break;
			}

			case 'simple': {
				if ($display_date != 'start_end') {
					if ( EL()->options->event->get('show_hours_archive', 'yes') == 'yes' ) {
						$html .= $weekdays_start . ', ' . $date_start_i18n . ', ' . $start_hour;
					} else {
						$html .= $weekdays_start . ', ' . $date_start_i18n;
					}
				} elseif ($date_start_i18n ==  $date_end_i18n ) {
					if ( EL()->options->event->get('show_hours_archive', 'yes') == 'yes' ) {
						$html .= $date_start_i18n . '(' . $start_hour . ' - ' . $end_hour . ')';
					} else {
						$html .= $date_start_i18n;
					}
				} else {
					$html .= $date_start_i18n . ' - ' . $date_end_i18n;
				}
				break;
			}

			case 'map_simple':
				if( $weekdays_start ){
					$html .= '<span class="start-time">' . $weekdays_start . ', ' . $date_start_i18n . '</span>';
				}else{
					$html .= '<span class="start-time">' . $date_start_i18n . '</span>';
				}
			

			break;

			default: {

			
				if ($display_date != 'start_end' ) {

					if( $weekdays_start ){
						$html .= '<span class="start-time">' . $weekdays_start . ', ' . $date_start_i18n . '</span>';
					}else{
						$html .= '<span class="start-time">' . $date_start_i18n . '</span>';
					}

					if ( EL()->options->event->get('show_hours_single', 'yes') == 'yes' && is_singular('event') ) {
						$html .= '<span >, ' . $start_hour .'</span>';
					}
					if ( EL()->options->event->get('show_hours_archive', 'yes') == 'yes' && !is_singular('event') ) {
						$html .= '<span >, ' . $start_hour .'</span>';
					}

				} elseif ($date_start_i18n ==  $date_end_i18n ) {

					$html .= '<span class="start-time">' . $date_start_i18n . '</span>';
					if ( EL()->options->event->get('show_hours_single', 'yes') == 'yes' && is_singular('event') ) {
						$html .= '<span class="end-time"> (' . $start_hour . ' - ' . $end_hour . ')</span>';
					}
					if ( EL()->options->event->get('show_hours_archive', 'yes') == 'yes' && !is_singular('event') ) {
						$html .= '<span class="end-time"> (' . $start_hour . ' - ' . $end_hour . ')</span>';
					}

				} else {
					$html .= '<span class="start-time">' . $date_start_i18n . '</span>';
					$html .= '<span class="separator"> - </span>';
					$html .= '<span class="end-time">' . $date_end_i18n . '</span>';
				}

			}
		}


		$html .= "<span class='timezone'>".el_get_timezone_event( $eid )."</span>";

		return $html;

	}

	
	public function getPostViews( $postID ){
		$count_key = 'post_views_count';
		$count = get_post_meta( $postID, $count_key, true );
		(int)$count;
		if( $count == 0 ){
			delete_post_meta($postID, $count_key);
			add_post_meta($postID, $count_key, 1);
			return 1;
		} else {
			$count += 1;
			update_post_meta( $postID, $count_key, $count );
			return $count;
		}
	}


	public function get_video_single_event () {
		$url_video = esc_url( get_post_meta( get_the_ID(), OVA_METABOX_EVENT . 'link_video', true) );
		$embed = '';
		if( strpos( $url_video, 'youtube.com/watch' ) ){
			$embed = preg_replace("/\s*[a-zA-Z\/\/:\.]*youtube.com\/watch\?v=([a-zA-Z0-9\-_]+)([a-zA-Z0-9\/\*\-\_\?\&\;\%\=\.]*)/i","<iframe  height=\"390\" src=\"//www.youtube.com/embed/$1\" frameborder=\"0\" allowfullscreen></iframe>",$url_video);	
		}else if( strpos( $url_video, 'youtu.be' ) ){

			$embed = preg_replace("/\s*[a-zA-Z\/\/:\.]*youtu.be\/([a-zA-Z0-9\-_]+)([a-zA-Z0-9\/\*\-\_\?\&\;\%\=\.]*)/i","<iframe  height=\"390\" src=\"//www.youtube.com/embed/$1\" frameborder=\"0\" allowfullscreen></iframe>",$url_video);

		}elseif($url_video){
			$embed = '<iframe height="390" src="'.$url_video.'"></iframe>';	
		}
		
		return $embed;
	}

	public function get_gallery_single_event ( $img_size = '' ) {
		$img_tmb = has_image_size( $img_size ) ? $img_size : '';
		$list_id_images = get_post_meta ( get_the_ID(), OVA_METABOX_EVENT . 'gallery', true );
		$list_url_images = [];
		if (!empty($list_id_images)) {
			foreach ($list_id_images as $id_image) {
				
				$img_property = wp_get_attachment_image_src( $id_image, $img_tmb );

				if( $img_tmb == 'el_thumbnail_gallery' && $img_property[3] == false ){
					$list_url_images[] = wp_get_attachment_image_url( $id_image , 'el_thumbnail');
				}else if( $img_tmb == 'el_large_gallery' && $img_property[3] == false ){
					$list_url_images[] = wp_get_attachment_image_url( $id_image , 'el_img_squa');
				}else{
					$list_url_images[] = wp_get_attachment_image_url( $id_image , $img_tmb);	
				}

			}
		}
		return $list_url_images;
	}


	public function get_status_ticket_info_by_date_and_time( $start_date = 0, $start_time = 0, $end_date = 0, $end_time = 0, $event_id = null ) {
		$start_time 	= el_get_time_int_by_date_and_hour( $start_date,  $start_time);
		$end_time 		= el_get_time_int_by_date_and_hour( $end_date,  $end_time);
		$current_time 	= current_time('timestamp');

		if ( $event_id ) {
			$timezone = get_post_meta( $event_id, OVA_METABOX_EVENT . 'time_zone', true );

			if ( $timezone ) {
				$tz_string 	= el_get_timezone_string( $timezone );
				$datetime 	= new DateTime('now', new DateTimeZone( $tz_string ) );
				$time_now 	= $datetime->format('Y-m-d H:i');

				if ( strtotime( $time_now ) ) {
					$current_time = strtotime( $time_now );
				}
			}
		}

		if ( $current_time < $start_time ) {
			return esc_html__( "Tickets are available from", "eventlist" ).' '.date_i18n( get_option('date_format').' '.get_option( 'time_format' ) , $start_time );
		} elseif ( $current_time < $end_time && $current_time >= $start_time ) {
			return esc_html__( "Selling", "eventlist" );
		} else {
			return esc_html__( "Online booking closed", "eventlist" );
		}
	}


	public function get_date_by_format_and_date_time ( $format = "Y-m-d", $date = 0, $time = 0 ) {
		$time_total = el_get_time_int_by_date_and_hour($date, $time);
		return date_i18n( $format, $time_total );
	}


	public static function get_seat_option( $id ){
		if( !$id ) return null;
		return get_post_meta( $id, OVA_METABOX_EVENT.'seat_option', true );
	}


	public static function el_get_event( $id ){
		if( !$id ) return false;
		return get_post( $id );
	}


	public static function el_get_calendar_date( $id_event, $id_cal ){
		if( !$id_event || !$id_cal ) return false;

		$list_calendar = get_arr_list_calendar_by_id_event($id_event);

		if( is_array($list_calendar) && !empty($list_calendar) ){
			foreach ($list_calendar as $cal) {
				if( $cal['calendar_id'] == $id_cal ) {
					return $cal['date'];
					break;
				}
			}
		}
		return;
	}


	public static function is_ticket_type_exist( $id_event, $id_cal, $cart , $coupon = ""){

		if( !$id_event || !$id_cal || !$cart ) return false;

		$list_type_ticket = get_post_meta( $id_event, OVA_METABOX_EVENT . 'ticket', true);
		$list_id_ticket = $list_price_ticket = $list_qty_ticket_rest = $list_date_time_ticket = $list_qty_min_ticket = $list_qty_max_ticket = $list_choose_seat = [];
		if ( !empty ($list_type_ticket) && is_array($list_type_ticket) ) {
			foreach ($list_type_ticket as $tiket) {
				$list_id_ticket[] = $tiket['ticket_id'];

				$list_qty_ticket_rest[$tiket['ticket_id']] = EL_Booking::instance()->get_number_ticket_rest($id_event, $id_cal, $tiket['ticket_id']);

				$list_qty_min_ticket[$tiket['ticket_id']] = (int)$tiket['number_min_ticket'];
				$list_qty_max_ticket[$tiket['ticket_id']] = (int)$tiket['number_max_ticket'];
				$list_choose_seat[$tiket['ticket_id']] = $tiket['setup_seat'];

				$list_price_ticket[$tiket['ticket_id']] = !empty($tiket['price_ticket']) ? floatval( $tiket['price_ticket'] ) : 0;
				$list_date_time_ticket[$tiket['ticket_id']] = [
					'start_date' => $tiket['start_ticket_date'],
					'start_time' => $tiket['start_ticket_time'],
					'end_date' => $tiket['close_ticket_date'],
					'end_time' => $tiket['close_ticket_time'],
				];
			}
		}

		if ( !empty ($cart) && is_array($cart) ) {

			foreach ( $cart as $key => $value ) {

				//check ticket isset in event
				if ( ! in_array( $value['id'],  $list_id_ticket ) ) {
					EL()->msg_session->set( 'el_message',   __("Ticket Type does not exists","eventlist") );
					return false;
				}

				//check ticket open
				$is_time_open = EL_Cart::instance()->is_booking_ticket_by_date_time( $list_date_time_ticket[$value['id']]['start_date'], $list_date_time_ticket[$value['id']]['start_time'], $list_date_time_ticket[$value['id']]['end_date'], $list_date_time_ticket[$value['id']]['end_time'], $id_event );
				
				if ( ! $is_time_open ) {
					EL()->msg_session->set( 'el_message',  __("Ticket Type closed","eventlist") );
					return false;
				}

				//check price ticket type
				if ( floatval( $cart[$key]['price'] ) !== floatval( $list_price_ticket[$value['id']] ) ) {
					EL()->msg_session->set( 'el_message',   __("Some thing went wrong price","eventlist") );
					return false;
				}

				//check qty ticket
				if ( $cart[$key]['qty'] > $list_qty_ticket_rest[$value['id']]  || $cart[$key]['qty'] < $list_qty_min_ticket[$value['id']] || $cart[$key]['qty'] > $list_qty_max_ticket[$value['id']] )  {
					EL()->msg_session->set( 'el_message',  __("Ticket limited","eventlist") );
					return false;
				}

				// check seat simple exists seat
				$seat_option = get_seat_option( $id_event );
				if ( $seat_option == 'simple' ) {
					if ($list_choose_seat[$value['id']] == 'yes') {
						if ( ! EL_Booking::instance()->check_seat_in_cart($value['seat'], $id_event, $id_cal, $value['id']) ) {
							EL()->msg_session->set( 'el_message',  __("Some thing went wrong seat","eventlist") );
							return false;
						}
					} elseif ($list_choose_seat[$value['id']] == 'no') {
						$list_seat_booking = EL_Booking::instance()->auto_book_seat_of_ticket($id_event, $id_cal, $value['id'], $value['qty']);
						if (empty($list_seat_booking)) {
							EL()->msg_session->set( 'el_message',  __("Full Seat","eventlist") );
							return false;
						}
					}
				}
			}
		}
		
		//check discount
		if ( !empty( $coupon ) ) {
			$data_coupon = EL_Cart::instance()->check_code_discount($id_event, $coupon);
			if ( ! $data_coupon ) {
				$this->session_msg_error = EL_Sessions::instance( 'el_message_error' );
				$this->session_msg_error->set( 'el_message', __("Coupon error","eventlist") );
				return false;
			}
		}

		return true;
	}

	public static function is_seat_map_exist( $id_event, $id_cal, $cart , $coupon = "" ) {
		if ( ! $id_event || ! $id_cal || ! $cart ) return false;

		$ticket_map = get_post_meta( $id_event, OVA_METABOX_EVENT . 'ticket_map', true) ? get_post_meta( $id_event, OVA_METABOX_EVENT . 'ticket_map', true ) : array();
		$data_ids = $list_seat_ids = $list_area_ids = $list_price_seat = $list_qty_min_ticket = $list_qty_max_ticket = [];

		$ticket_ids_datetime 	= [];
		$data_seat_price 		= [];

		if ( isset( $ticket_map['seat'] ) && ! empty( $ticket_map['seat'] ) && is_array( $ticket_map['seat'] ) ) {
			foreach ( $ticket_map['seat'] as $value ) {
				foreach ( explode(",", $value['id'] ) as $v ) {
					$ticket_id 			= trim($v);
					$list_seat_ids[] 	= $ticket_id;
					$data_seat_price[$ticket_id] = $value['price'];

					if ( isset( $value['start_date'] ) && $value['start_date'] && isset( $value['end_date'] ) && $value['end_date'] ) {
						$ticket_ids_datetime[$ticket_id] = array(
							'start_date' 	=> $value['start_date'],
							'start_time' 	=> isset( $value['start_time'] ) ? $value['start_time'] : '',
							'end_date' 		=> $value['end_date'],
							'end_time' 		=> isset( $value['end_time'] ) ? $value['end_time'] : '',
						);
					}
				}
			}
		}

		if ( isset( $ticket_map['area'] ) && ! empty( $ticket_map['area'] ) && is_array( $ticket_map['area'] ) ) {
			foreach ( $ticket_map['area'] as $value ) {
				$ticket_id 			= trim( $value['id'] );
				$list_area_ids[] 	= $ticket_id;
				$data_seat_price[$ticket_id] = $value['price'];

				if ( isset( $value['start_date'] ) && $value['start_date'] && isset( $value['end_date'] ) && $value['end_date'] ) {
					$ticket_ids_datetime[$ticket_id] = array(
						'start_date' 	=> $value['start_date'],
						'start_time' 	=> isset( $value['start_time'] ) ? $value['start_time'] : '',
						'end_date' 		=> $value['end_date'],
						'end_time' 		=> isset( $value['end_time'] ) ? $value['end_time'] : '',
					);
				}
			}
		}

		$data_ids = array_unique( array_merge( $list_seat_ids, $list_area_ids ) );

		if ( ! empty( $cart ) && is_array( $cart ) ) {
			$list_seat_error 	= [];
			$area_available 	= EL_Booking::instance()->el_get_area_qty_available( $id_event, $id_cal );
			$start_date = $start_time = $end_date = $end_time = '';

			foreach ( $cart as $key => $value ) {
				// Check Ticket Isset In Event
				if ( ! in_array( $value['id'], $data_ids ) ) {
					EL()->msg_session->set( 'el_message', sprintf( esc_html__("%s does not exists", "eventlist"), $value['id'] ) );

					return false;
				}

				// Check Ticket Open
				if ( array_key_exists( $value['id'], $ticket_ids_datetime ) ) {
					$start_date = $ticket_ids_datetime[trim($value['id'])]['start_date'];
					$start_time = $ticket_ids_datetime[trim($value['id'])]['start_time'];
					$end_date 	= $ticket_ids_datetime[trim($value['id'])]['end_date'];
					$end_time 	= $ticket_ids_datetime[trim($value['id'])]['end_time'];
				} else {
					if ( ! empty( $ticket_map ) ) {
						$start_date = $ticket_map['start_ticket_date'];
						$start_time = $ticket_map['start_ticket_time'];
						$end_date 	= $ticket_map['close_ticket_date'];
						$end_time 	= $ticket_map['close_ticket_time'];
					}
				}

				$is_time_open = EL_Cart::instance()->is_booking_ticket_by_date_time( $start_date, $start_time, $end_date, $end_time, $id_event );

				if ( ! $is_time_open ) {
					EL()->msg_session->set( 'el_message', sprintf( esc_html__("Ticket %s closed", "eventlist"), $value['id'] ) );
					return false;
				}

				// Check Seat Price
				if ( isset( $data_seat_price[$value['id']] ) && $data_seat_price[$value['id']] ) {
					if ( $value['price'] != $data_seat_price[$value['id']] ) {
						EL()->msg_session->set( 'el_message', esc_html__("Some thing went wrong price", "eventlist") );
						return false;
					}
				}

				// Check Seat, Area exists
				if ( ! EL_Booking::instance()->check_seat_map_in_cart( $value['id'], $id_event, $id_cal ) ) {
					$list_seat_error[] = $value['id'];
				}

				// Check qty area available
				if ( in_array( $value['id'], $list_area_ids ) ) {
					$area_cart_qty = isset( $value['qty'] ) ? absint( $value['qty'] ) : 0;
					$area_qty_available = isset( $area_available[$value['id']] ) ? absint( $area_available[$value['id']] ) : 0;

					if ( $area_cart_qty > $area_qty_available ) {
						EL()->msg_session->set( 'el_option', 'map' );
						
						if ( $area_qty_available > 0 ) {
							EL()->msg_session->set( 'el_message', sprintf( esc_html__( "Maximum %s: %s","eventlist" ), $value['id'], $area_qty_available ) );
						} else {
							EL()->msg_session->set( 'el_message', sprintf( esc_html__( "%s is out of stock","eventlist" ), $value['id'] ) );
						}
						EL()->msg_session->set( 'el_reload_page', esc_html__("Click here to reload the page or the page will automatically reload after 5 seconds.", "eventlist") );
						EL()->msg_session->set( 'el_content', [ $value['id'] ] );

						return false;
					}
				}
			}

			if ( ! empty( $list_seat_error ) && is_array( $list_seat_error ) ) {
				$list_seat_error = array_unique( $list_seat_error );

				EL()->msg_session->set( 'el_option', 'map' );
				EL()->msg_session->set( 'el_message', sprintf( esc_html__("Some thing went wrong seat: %s","eventlist"), esc_html( implode(", ", $list_seat_error) ) ) );
				EL()->msg_session->set( 'el_reload_page', esc_html__("Click here to reload the page or the page will automatically reload after 5 seconds.", "eventlist") );
				EL()->msg_session->set( 'el_content', $list_seat_error );

				return false;
			}
		}

		// Check discount
		if ( ! empty( $coupon ) ) {
			$data_coupon = EL_Cart::instance()->check_code_discount( $id_event, $coupon );

			if ( ! $data_coupon ) {
				$this->session_msg_error = EL_Sessions::instance( 'el_message_error' );
				$this->session_msg_error->set( 'el_message', esc_html__("Coupon error","eventlist") );
				return false;
			}
		}

		return true;
	}


	public function get_status_event_calendar ($time_start = 0, $time_end = 0, $number_time = 0, $event_id = null ) {
		$status = "";
		$current_time = current_time('timestamp') + $number_time;

		if ( $event_id ) {
			$timezone = get_post_meta( $event_id, OVA_METABOX_EVENT . 'time_zone', true );

			if ( $timezone ) {
				$tz_string 	= el_get_timezone_string( $timezone );
				$datetime 	= new DateTime('now', new DateTimeZone( $tz_string ) );
				$time_now 	= $datetime->format('Y-m-d H:i');

				if ( strtotime( $time_now ) ) {
					$current_time = strtotime( $time_now ) + $number_time;
				}
			}
		}

		if ( $time_start !== 0 &&  $time_end !== 0) {
			if ( $current_time < $time_start) {
				$status = esc_html__( "Upcoming", "eventlist" );
			} else if ( $current_time > $time_start && $current_time < $time_end ) {
				$status = esc_html__( "Opening", "eventlist" );
			} else {
				$status = esc_html__( "Closed", "eventlist" );
			}
		}
		return $status;
	}


	/**
	 * Search Event
	 */
	public static function el_search_event($params) {
		
		$_prefix = OVA_METABOX_EVENT;

		$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

		$name = isset( $params['name_event'] ) ? esc_html( $params['name_event'] ) : '' ;
		$cat = isset( $params['cat'] ) ? esc_html( $params['cat'] ) : '' ;
		$name_venue = isset( $params['name_venue'] ) ? esc_html( $params['name_venue'] ) : '' ;
		$time = isset( $params['time'] ) ? esc_html( $params['time'] ) : '' ;
		$start_date = isset( $params['start_date'] ) ? esc_html( $params['start_date'] ) : '' ;
		$end_date = isset( $params['end_date'] ) ? esc_html( $params['end_date'] ) : '' ;
		$loc_input = isset( $params['loc_input'] ) ? esc_html( $params['loc_input'] ) : '' ;

		$event_state = isset( $params['event_state'] ) ? esc_html( $params['event_state'] ) : '' ;
		$event_city = isset( $params['event_city'] ) ? esc_html( $params['event_city'] ) : '' ;

		$event_type = isset( $params['event_type'] ) ? esc_html( $params['event_type'] ) : '' ;

    	// Init query
		$args_basic = $args_name = $args_venue = $args_time = $args_tax = $args_date = $args_state = $args_city = $args_location = $args_event_type = $args_filter_events = array();

		$orderby = EL()->options->event->get('archive_order_by') ? EL()->options->event->get('archive_order_by') : 'title';
		$order = EL()->options->event->get('archive_order') ? EL()->options->event->get('archive_order') : 'DESC';
		$listing_posts_per_page = EL()->options->event->get('listing_posts_per_page') ? EL()->options->event->get('listing_posts_per_page') : '12';

		$filter_events = EL()->options->event->get('filter_events', 'all');

		$args_order = array( 'order' => $order );
		// Query base
		$args_base = array(
			'post_type'      => 'event',
			'paged'          => $paged,
			'posts_per_page' => $listing_posts_per_page
		);

		switch ( $orderby ) {
			case 'title':
				$args_orderby =  array( 'orderby' => 'title' );
			break;

			case 'start_date':
				$args_orderby =  array( 'orderby' => 'meta_value_num', 'meta_key' => $_prefix.'start_date_str' );
			break;

			case 'end_date':
				$args_orderby =  array( 'orderby' => 'meta_value_num', 'meta_key' => $_prefix.'start_date_str' );
			break;

			case 'near':
				$args_orderby = array( 'orderby' => 'post__in');
				$args_order = array( 'order' => 'ASC' );
			break;

			case 'date_desc':
				$args_orderby =  array( 'orderby' => 'date' );
				$args_order = array( 'order' => 'DESC' );
			break;

			case 'date_asc':
				$args_orderby = array( 'orderby' => 'date' );
				$args_order = array( 'order' => 'ASC' );
			break;

			case 'ID':
				$args_orderby =  array( 'orderby' => 'ID');
			break;

			default:
			break;
		}

		$args_basic = array_merge_recursive( $args_base, $args_orderby, $args_order );

		// Query Name
		if($name){
			$args_name = array( 's' => $name );
		}

		// Query Taxonomy Customize
		$list_taxonomy = EL_Post_Types::register_taxonomies_customize();
	    $arg_taxonomy_arr = [];
	    if ( ! empty( $list_taxonomy ) ) {
	        foreach( $list_taxonomy as $taxonomy ) {
	            $taxonomy_search = isset( $params[$taxonomy['slug']] ) ? sanitize_text_field( $params[$taxonomy['slug']] ) : '';

	            if ( $taxonomy_search != '' ) {
	                $arg_taxonomy_arr[] = array(
                		'taxonomy' => $taxonomy['slug'],
	                    'field' => 'slug',
	                    'terms' => $taxonomy_search
	                );
	            } else {
	                $arg_taxonomy_arr[] = '';
	            }
	        }
	    }

	    if( !empty($arg_taxonomy_arr) ){
	        $arg_taxonomy_arr = array(
	            'tax_query' => $arg_taxonomy_arr
	        );
	    }


		// Query Taxonomy
		if($cat){
			$args_tax = array(
				'tax_query' => array(
					array(
						'taxonomy' => 'event_cat',
						'field'    => 'slug',
						'terms' => $cat
					)
				)
			);
		}

		// Query Venue
		if($name_venue){
			$args_venue = array(
				'meta_query' => array(
					array(
						'key' => $_prefix.'venue',
						'value' => $name_venue,
						'compare' => 'LIKE'
					)
				)
			);
		}

		// Query Location input
		if($loc_input){
			$args_location = array(
				'tax_query' => array(
					array(
						'taxonomy' => 'event_loc',
						'field'    => 'slug',
						'terms' => $loc_input
					)
				)
			);
		}

		// Query State
		if($event_state){
			$args_state = array(
				'tax_query' => array(
					array(
						'taxonomy' => 'event_loc',
						'field'    => 'slug',
						'terms' => $event_state
					)
				)
			);
		}

		// Query City
		if($event_city){
			$args_city = array(
				'tax_query' => array(
					array(
						'taxonomy' => 'event_loc',
						'field'    => 'slug',
						'terms' => $event_city
					)
				)
			);
		}

		// Query Time
		if($time){

			$date_format = 'Y-m-d 00:00';
			$today_day = current_time( $date_format);

			// Return number of current day
			$num_day_current = date('w', strtotime( $today_day ) );

			// Check start of week in wordpress
			$start_of_week = get_option('start_of_week');

			// This week
			$week_start = date( 'Y-m-d', strtotime($today_day) - ( ($num_day_current - $start_of_week) *24*60*60) );
			$week_end = date( 'Y-m-d', strtotime($today_day)+ (7 - $num_day_current + $start_of_week )*24*60*60 );
			$this_week = el_getDatesFromRange( $week_start, $week_end );
			$this_week_regexp = implode( '|', $this_week );
			

			// Get Saturday in this week
			$saturday = strtotime( date($date_format, strtotime('this Saturday')));
			// Get Sunday in this week
			$sunday = strtotime( date( $date_format, strtotime('this Sunday')));
			// Weekend
			$week_end = array( strtotime( date( 'Y-m-d', $saturday ) ), strtotime( date( 'Y-m-d', $sunday ) ) );
			$week_end_regexp = implode( '|', $week_end );

			


			// Next week Start
			$next_week_start = strtotime($today_day)+ (7 - $num_day_current + $start_of_week )*24*60*60;
			// Next week End
			$next_week_end = $next_week_start+7*24*60*60;
			
			// Next week
			$next_week = el_getDatesFromRange( date( 'Y-m-d', $next_week_start ), date( 'Y-m-d', $next_week_end ) );
			$next_week_regexp = implode( '|', $next_week );
			

			// Month Current
			$num_day_current = date('n', strtotime( $today_day ) );

			// First day of next month
			$first_day_next_month = strtotime( date( $date_format, strtotime('first day of next month') ) );
			$last_day_next_month = strtotime ( date( $date_format, strtotime('last day of next month') ) )+24*60*60+1;
			// Next month
			$next_month = el_getDatesFromRange( date( 'Y-m-d', $first_day_next_month ), date( 'Y-m-d', $last_day_next_month ) );
			$next_month_regexp = implode( '|', $next_month );
			
			
			

			switch ($time) {
				case 'today':
				$args_time = array(
					'meta_query' => array(
						array(
							'key' => $_prefix.'event_days',
							'value' => strtotime($today_day),
							'compare' => 'LIKE'	
						),
					)
				);

				break;

				case 'tomorrow':
				$args_time = array(
					'meta_query' => array(
						array(
							'key' => $_prefix.'event_days',
							'value' => strtotime($today_day) + 24*60*60,
							'compare' => 'LIKE'	
						),
					)
				);
				break;

				case 'this_week':
				$args_time = array(
					'meta_query' => array(
						array(
							'key' => $_prefix.'event_days',
							'value' => $this_week_regexp,
							'compare' => 'REGEXP'	
						),
					)
				);
				break;

				case 'this_week_end':
				$args_time = array(
					'meta_query' => array(
						array(
							'key' => $_prefix.'event_days',
							'value' => $week_end_regexp,
							'compare' => 'REGEXP'	
						),
					)
				);
				break;

				case 'next_week':
				$args_time = array(
					'meta_query' => array(
						array(
							'key' => $_prefix.'event_days',
							'value' => $next_week_regexp,
							'compare' => 'REGEXP'	
						),
					)
				);
				break;

				case 'next_month':
				$args_time = array(
					'meta_query' => array(
						array(
							'key' => $_prefix.'event_days',
							'value' => $next_month_regexp,
							'compare' => 'REGEXP'	
						),
					)
				);
				break;

				default:
					# code...
				break;
			}
		}

		// Query Date
		if( $start_date && $end_date ){

			$between_dates = el_getDatesFromRange( date( 'Y-m-d', strtotime( $start_date ) ), date( 'Y-m-d', strtotime( $end_date ) + 24*60*60 ) );
			$between_dates_regexp = implode( '|', $between_dates );

			$args_date = array(
				'meta_query' => array(
					array(
						'key' => $_prefix.'event_days',
						'value' => $between_dates_regexp,
						'compare' => 'REGEXP'
					),
				)
			);

		}else if( $start_date || $end_date ){

			$args_date = array(
				'meta_query' => array(
					array(
						'relation' => 'OR',
						array(
							'key' => $_prefix.'event_days',
							'value' => strtotime( $start_date ),
							'compare' => 'LIKE'
						),
						array(
							'key' => $_prefix.'event_days',
							'value' => strtotime( $end_date ),
							'compare' => 'LIKE'
						),
					)
				)	
			);

		}

		if( $event_type ){
			$args_event_type = array(
				'meta_query' => array(
					array(
						'key' => $_prefix.'event_type',
						'value' => $event_type,
						'compare' => 'LIKE'	
					),
				)
			);
		}

		$max_price = isset( $params['el_max_price'] ) ? sanitize_text_field( $params['el_max_price'] ) : '';
		$min_price = isset( $params['el_min_price'] ) ? sanitize_text_field( $params['el_min_price'] ) : '';

		$args_filter_price = array();
		if ( $max_price != '' && $min_price != '' ) {
			$decimals = (int) EL()->options->general->get('number_decimals',2);

			$max_price_format = (float) $max_price;
			$max_price_format = round($max_price_format,$decimals);

			$min_price_format = (float) $min_price;
			$min_price_format = round($min_price_format,$decimals);

			$args_filter_price = array(
				'meta_query' => array(
					array(
						'relation' => 'OR',
						array(
							'key' => OVA_METABOX_EVENT.'min_price',
							'value' => array($min_price_format, $max_price_format),
							'compare' => 'BETWEEN',
							'type' => 'DECIMAL',
						),
						array(
							'key' => OVA_METABOX_EVENT.'max_price',
							'value' => array($min_price_format, $max_price_format),
							'compare' => 'BETWEEN',
							'type' => 'DECIMAL',
						),
					),
				),
			);
		}

		// Query filter
		$args_filter_events = el_sql_filter_status_event( $filter_events );

		$args = array_merge_recursive( $args_basic, $args_venue, $args_name, $args_time, $args_tax, $args_date, $args_state, $args_city, $args_location, $args_filter_events, $args_event_type, $arg_taxonomy_arr, $args_filter_price );

		$events = new WP_Query( apply_filters( 'el_search_event_query', $args, $params )  );

		return $events;
	}


	public static function el_search_event_map($show_featured = '') {
		$_prefix = OVA_METABOX_EVENT;

		$orderby = EL()->options->event->get('archive_order_by') ? EL()->options->event->get('archive_order_by') : 'title';
		$order = EL()->options->event->get('archive_order') ? EL()->options->event->get('archive_order') : 'DESC';
		$listing_posts_per_page = EL()->options->event->get('listing_posts_per_page') ? EL()->options->event->get('listing_posts_per_page') : '12';

		$filter_events = EL()->options->event->get('filter_events', 'all');

		$args_base = array(
			'post_type'      => 'event',
			'post_status'    => 'publish',
			'posts_per_page' => $listing_posts_per_page,
		);

		$args_order = array( 'order' => $order ); 
		$args_filter_events = $args_orderby = array();

		switch ( $orderby ) {
			case 'title':
				$args_orderby =  array( 'orderby' => 'title' );
			break;

			case 'start_date':
				$args_orderby =  array( 'orderby' => 'meta_value_num', 'meta_key' => $_prefix.'start_date_str' );
			break;

			case 'end_date':
				$args_orderby =  array( 'orderby' => 'meta_value_num', 'meta_key' => $_prefix.'start_date_str' );
			break;

			case 'near':
				$args_orderby = array( 'orderby' => 'post__in');
				$args_order = array( 'order' => 'ASC' );
			break;

			case 'date_desc':
				$args_orderby =  array( 'orderby' => 'date' );
				$args_order = array( 'order' => 'DESC' );
			break;

			case 'date_asc':
				$args_orderby = array( 'orderby' => 'date' );
				$args_order = array( 'order' => 'ASC' );
			break;

			case 'ID':
				$args_orderby =  array( 'orderby' => 'ID');
			break;

			default:
			break;
		}


		// Query filter
		$args_filter_events = el_sql_filter_status_event( $filter_events );

		/* Show Featured */
		if ($show_featured == 'yes') {
			$args_featured = array(
				'meta_key' =>  OVA_METABOX_EVENT.'event_feature',
				'meta_query'=> array(
					array(
						'key' =>  OVA_METABOX_EVENT.'event_feature',
						'compare' => '=',
						'value' => 'yes',
					)
				)
			);
		} else {
			$args_featured = array();
		}

		$args = array_merge_recursive( $args_base, $args_filter_events, $args_orderby, $args_order ,$args_featured );


		$events = new WP_Query( $args );

		return $events;
	}


	/**
	 * Get All Event with Paged
	 */
	public static function el_get_all_event() {
		$_prefix = OVA_METABOX_EVENT;

		$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

		$orderby = EL()->options->event->get('archive_order_by') ? EL()->options->event->get('archive_order_by') : 'title';
		$order = EL()->options->event->get('archive_order') ? EL()->options->event->get('archive_order') : 'DESC';
		$listing_posts_per_page = EL()->options->event->get('listing_posts_per_page') ? EL()->options->event->get('listing_posts_per_page') : '12';
		$args_order = array( 'order' => $order );
		// Query base
		$args_base = array(
			'post_type'      => 'event',
			'paged'          => $paged,
			'posts_per_page' => $listing_posts_per_page
		);

		switch ( $orderby ) {
			case 'title':
				$args_orderby =  array( 'orderby' => 'title' );
			break;

			case 'start_date':
				$args_orderby =  array( 'orderby' => 'meta_value_num', 'meta_key' => $_prefix.'start_date_str' );
			break;

			case 'end_date':
				$args_orderby =  array( 'orderby' => 'meta_value_num', 'meta_key' => $_prefix.'start_date_str' );
			break;

			case 'near':
				$args_orderby = array( 'orderby' => 'post__in');
				$args_order = array( 'order' => 'ASC' );
			break;

			case 'date_desc':
				$args_orderby =  array( 'orderby' => 'date' );
				$args_order = array( 'order' => 'DESC' );
			break;

			case 'date_asc':
				$args_orderby = array( 'orderby' => 'date' );
				$args_order = array( 'order' => 'ASC' );
			break;

			case 'ID':
				$args_orderby =  array( 'orderby' => 'ID');
			break;

			default:
			break;
		}

		$args_basic = array_merge_recursive( $args_base, $args_orderby, $args_order );

		$events = new WP_Query( $args_basic );

		return $events;
	}

	/**
	 * Get All Event no pagination
	 */
	public static function el_all_events( $post_status = array( 'publish' ) ) {
		
		$order = EL()->options->event->get('archive_order') ? EL()->options->event->get('archive_order') : 'DESC';

		// Query base
		$args_base = array(
			'post_type'      => 'event',
			'order'          => 'ASC',
			'orderby' 		=> 'title',
			'numberposts' => '-1',
			'post_status' => $post_status
		);
		

		$events = get_posts( $args_base );

		return $events;
	}


	public static function get_list_venue_first_letter($filter = "", $paged = 1) {
		$agrs = [
			'post_type' => 'venue',
			'orderby' => 'title',
			'order' => 'ASC',
			'post_status' => 'publish',
			'starts_with' => $filter,
			'paged' => $paged,
		];
		$venues = new WP_Query( $agrs );
		return apply_filters('el_list_venue_first_letter', $venues);
	}


	public static function get_list_event_by_title_venue($venue = "", $paged = 1) {
		$order = EL()->options->event->get( 'archive_order' ) ? EL()->options->event->get( 'archive_order' ) : 'DESC';
		$orderby = EL()->options->event->get( 'archive_order_by' ) ? EL()->options->event->get( 'archive_order_by' ) : 'title';

		$agrs_base = [
			'post_type' => 'event',
			'post_status' => 'publish',
			'paged' => $paged,
			'meta_query' => array(
				'relation' => 'AND',
		        array(
		            'key'     => OVA_METABOX_EVENT . 'venue',
		            'value'   => $venue,
		            'compare' => 'LIKE',
		        ),
		    ),
		];

		if ( $orderby !== 'start_date' ) {
			$agrs_order = [
				'orderby' =>  $orderby,
				'order'  =>  $order,
			];
		} else {
			$agrs_order = [
				'orderby' => ['meta_value_num' => $order],
				'meta_key' => OVA_METABOX_EVENT . 'start_date_str',
			];
		}


		$filter_events = EL()->options->event->get('filter_events', 'all');
		$args_filter_events = array();

		$args_filter_events = el_sql_filter_status_event( $filter_events );

		$agrs = array_merge_recursive( $agrs_base, $agrs_order, $args_filter_events );

		$events = new WP_Query( $agrs );
		return apply_filters('el_list_event_by_title_venue', $events);
	}


	public static function get_list_event_close_diplay_profit ($filter = null, $paged = 1) {
		$number_day_display = $percent_tax = EL()->options->tax_fee->get('x_day_profit');
		$time_days_setting = (int)$number_day_display * 24* 3600;
		$current_time = current_time('timestamp');
		$time = $current_time - $time_days_setting;

		switch ($filter) {
			case "pending" : {
				$meta_query = [
					'relation' => 'AND',
					[
						"key" => OVA_METABOX_EVENT . 'end_date_str',
						"value" => $time,
						"compare" => "<=",
					],
					[
						"key" => OVA_METABOX_EVENT . 'status_pay',
						"value" => 'pending',
					],
				];
				break;
			}
			case "paid" : {
				$meta_query = [
					'relation' => 'AND',
					[
						"key" => OVA_METABOX_EVENT . 'end_date_str',
						"value" => $time,
						"compare" => "<=",
					],
					[
						"key" => OVA_METABOX_EVENT . 'status_pay',
						"value" => 'paid',
					],
				];
				break;
			}
			default : {
				$meta_query = [
					'relation' => 'AND',
					[
						"key" => OVA_METABOX_EVENT . 'end_date_str',
						"value" => $time,
						"compare" => "<=",
					],
				];
				break;
			}
		}

		$agrs = [
			'post_type' => 'event',
			'post_status' => 'publish',
			'orderby' => 'meta_value',
			'meta_key' => OVA_METABOX_EVENT . 'status_pay',
			'orderby' => ['meta_value' => 'DESC', 'date' => 'DESC' ],
			"meta_query" => $meta_query,
			"paged" => $paged,
		];

		$events = new WP_Query( $agrs );
		return apply_filters('el_get_list_event_close_diplay_profit', $events);
	}

	function check_ticket_in_event_selling( $id_event = null ) {
		if ( $id_event == null ) return false;

		$seat_option 	= get_post_meta( $id_event, OVA_METABOX_EVENT . 'seat_option', true);
		$current_time 	= el_get_current_time_by_event( $id_event );
		$selling 		= [];

		if ( $seat_option != 'map' ) {
			$list_type_ticket = get_post_meta( $id_event, OVA_METABOX_EVENT . 'ticket', true );

			if ( ! empty( $list_type_ticket ) ) {
				foreach ( $list_type_ticket as $ticket ) {

					if ( isset( $ticket['start_ticket_date'] ) && isset( $ticket['start_ticket_time'] ) ) {
						$start_time = el_get_time_int_by_date_and_hour( $ticket['start_ticket_date'], $ticket['start_ticket_time'] );	
					} else {
						$start_time = 0 ;
					}
					
					if ( isset( $ticket['close_ticket_date'] ) && isset( $ticket['close_ticket_time'] ) ) {
						$end_time = el_get_time_int_by_date_and_hour( $ticket['close_ticket_date'], $ticket['close_ticket_time'] );
					} else {
						$end_time = 0;
					}

					if ( $current_time < $end_time && $current_time >= $start_time ) {
						$selling[] = 'selling';
					}

				}
			}
		} else {
			$ticket_map = get_post_meta( $id_event, OVA_METABOX_EVENT . 'ticket_map', true );

			if ( isset( $ticket_map['start_ticket_date'] ) && isset( $ticket_map['start_ticket_time'] ) ) {
				$start_time = el_get_time_int_by_date_and_hour( $ticket_map['start_ticket_date'], $ticket_map['start_ticket_time'] );
			} else {
				$start_time = 0 ;
			}

			if ( isset( $ticket_map['close_ticket_date'] ) && isset( $ticket_map['close_ticket_time'] ) ) {
				$end_time = el_get_time_int_by_date_and_hour( $ticket_map['close_ticket_date'], $ticket_map['close_ticket_time'] );
			} else {
				$end_time = 0;
			}

			$data_seat = [];

			// Seats
			if ( isset( $ticket_map['seat'] ) && ! empty( $ticket_map['seat'] ) && isset( $ticket_map['seat'] ) ) {
				foreach ( $ticket_map['seat'] as $item_seat ) {
					$seat_start_time 	= $start_time;
					$seat_end_time 		= $end_time;

					if ( isset( $item_seat['start_date'] ) && isset( $item_seat['start_time'] ) ) {
						$seat_start_time = el_get_time_int_by_date_and_hour( $item_seat['start_date'], $item_seat['start_time'] );
					}

					if ( isset( $item_seat['end_date'] ) && isset( $item_seat['end_time'] ) ) {
						$seat_end_time = el_get_time_int_by_date_and_hour( $item_seat['end_date'], $item_seat['end_time'] );
					}

					if ( $current_time < $seat_end_time && $current_time >= $seat_start_time ) {
						$selling[] = 'selling';
						break;
					}
				}
			}

			// Areas
			if ( isset( $ticket_map['area'] ) && ! empty( $ticket_map['area'] ) && isset( $ticket_map['area'] ) ) {
				foreach ( $ticket_map['area'] as $area_item ) {
					$seat_start_time 	= $start_time;
					$seat_end_time 		= $end_time;

					if ( isset( $area_item['start_date'] ) && isset( $area_item['start_time'] ) ) {
						$seat_start_time = el_get_time_int_by_date_and_hour( $area_item['start_date'], $area_item['start_time'] );
					}

					if ( isset( $area_item['end_date'] ) && isset( $area_item['end_time'] ) ) {
						$seat_end_time = el_get_time_int_by_date_and_hour( $area_item['end_date'], $area_item['end_time'] );
					}

					if ( $current_time < $seat_end_time && $current_time >= $seat_start_time ) {
						$selling[] = 'selling';
						break;
					}
				}
			}
		}
		
		if ( in_array('selling', $selling) ) {
			return true;
		} else {
			return false;
		}
	}

	public static function el_get_event_slideshow($posts_per_page, $category, $filter, $featured, $orderby, $order) {
		$_prefix = OVA_METABOX_EVENT;

		$args_base = array(
			'fields' => 'ids',
			'post_type'      => 'event',
			'post_status'    => 'publish',
			'posts_per_page' => $posts_per_page,
			'order' => $order,
		);

		$args_orderby = $args_filter = $args_featured = $args_tax = array();

		switch ($orderby) {
			case 'title':
			$args_orderby =  array( 'orderby' => 'title' );
			break;

			case 'start_date':
			$args_orderby =  array( 'orderby' => 'meta_value_num', 'meta_key' => $_prefix.'start_date_str' );
			break;

			case 'ID':
			$args_orderby =  array( 'orderby' => 'ID');
			break;

			default:
			break;
		}

		if ( $filter == 'upcoming_current' ) {
			$args_filter = array(
				'meta_query' => array(
					array(
						'relation' => 'OR',
						array(
							'key' => $_prefix.'start_date_str',
							'value' => current_time( 'timestamp' ),
							'compare' => '>'
						),
						array(
							'relation' => 'AND',
							array(
								'key' => $_prefix.'start_date_str',
								'value' => current_time( 'timestamp' ),
								'compare' => '<'
							),
							array(
								'key' => $_prefix.'end_date_str',
								'value' => current_time( 'timestamp'),
								'compare' => '>='
							)
						)
					)

				)
			);

		} elseif( $filter == 'upcoming' ) {
			$args_filter = array(
				'meta_query' => array(
					array(
						'key' => $_prefix.'start_date_str',
						'value' => current_time( 'timestamp' ),
						'compare' => '>'
					),
				)
			);

		} elseif( $filter == 'current' ) {
			$args_filter = array(
				'meta_query' => array(
					array(
						'relation' => 'AND',
						array(
							'key' => $_prefix.'start_date_str',
							'value' => current_time( 'timestamp' ),
							'compare' => '<'
						),
						array(
							'key' => $_prefix.'end_date_str',
							'value' => current_time( 'timestamp'),
							'compare' => '>='
						)
					)
				)
			);

		} elseif( $filter == 'past' ) {
			$args_filter = array(
				'meta_query' => array(
					array(
						'key' => $_prefix.'end_date_str',
						'value' => current_time( 'timestamp' ),
						'compare' => '<'
					),
				)
			);

		} else {
			$args_filter = array();
		}

		if( $category ){
			$args_tax = array(
				'tax_query' => array(
					array(
						'taxonomy' => 'event_cat',
						'field'    => 'slug',
						'terms'    => explode( ',', $category ),
					),
				),
			);
		}

		if ( $featured ) {
			$args_featured = array(
				'meta_query' => array(
					array(
						'key' => $_prefix.'event_feature',
						'value' => 'yes',
						'compare' => '='
					)	
				)
			);
		}

		$args = array_merge_recursive( $args_base, $args_orderby, $args_filter, $args_featured, $args_tax );


		$events = new WP_Query( $args );

		return $events;
	}

	/***** Slideshow Simple Elementor *****/
	public static function el_get_event_slideshow_simple( $posts_per_page, $category = 'all', $filter_events = 'all', $orderby = 'id', $order = 'DESC' ) {

		$_prefix = OVA_METABOX_EVENT;

		$args_cat = array();
		if ($category != 'all') {
			$args_cat = array(
				'tax_query' => array(
					array(
						'taxonomy' => 'event_cat',
						'field'    => 'slug',
						'terms'    => $category,
					)
				)
			);
		}

		$args_base = array(
			'fields' => 'ids',
			'post_type'      => 'event',
			'post_status'    => 'publish',
			'posts_per_page' => $posts_per_page,
			'order' => $order,
		);

		$args_filter_events = $args_orderby = array();

		switch ($orderby) {
			case 'title':
			$args_orderby =  array( 'orderby' => 'title' );
			break;

			case 'start_date':
			$args_orderby =  array( 'orderby' => 'meta_value_num', 'meta_key' => $_prefix.'start_date_str' );
			break;

			case 'id':
			$args_orderby =  array( 'orderby' => 'ID');
			break;

			default:
			break;
		}

		// Query Show Opening, Show Past
		$args_filter_events = el_sql_filter_status_event( $filter_events );

		$args = array_merge_recursive( $args_base, $args_orderby, $args_filter_events, $args_cat );

		$events = new WP_Query( $args );

		return $events;

	}

	public static function el_get_event_type(){
		$id = get_the_id();
		return get_post_meta( $id, OVA_METABOX_EVENT . 'event_type', true);

	}

}
//end class EL_Event
