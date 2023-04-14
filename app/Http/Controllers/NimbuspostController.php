<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

class NimbuspostController extends Controller
{
    private $active = true;

    private static function print_r($data)
    {
        echo "<pre>";
        print_r($data);
        echo "</pre>";
        die;
    }

    private static function decode_format($data)
    {
        return json_decode($data, TRUE);
    }

    public function check_courier(Request $request)
    {
        // 
        $request_input = $request->only('origin_city', 'destination_city', 'origin_postal_code', 'destination_postal_code', 'origin_state', 'destination_state', "length", "width", "height", "weight", "quantity");
        
        // 
        $post_array = [
            'origin_city' => $request_input['origin_city'],
            'destination_city' => $request_input['destination_city'],
            'origin_postal_code' => $request_input['origin_postal_code'],
            'destination_postal_code' => $request_input['destination_postal_code'],
            'origin_state' => $request_input['origin_state'],
            'destination_state' => $request_input['destination_state'],
            'items' => [
                [
                    'length' => $request_input['length'],
                    'width' => $request_input['width'],
                    'height' => $request_input['height'],
                    'weight' => $request_input['weight'],
                    'quantity' => $request_input['quantity']
                ]
            ]
        ];

        // $post_array2 = json_encode($post_array);

        // self::print_r($post_array2);

        // 
        $data_array = [
            'regular' => [],
            'instant' => [],
            'sameday' => [],
            'express' => []
        ];

        // 
        $res = self::nimbuspost_curl('https://id.nimbuspost.com/api/couriers/shipping_rates', $post_array, true, 'post');
        
        // 
        $res2 = DB::table('couriers')->get();

        // 
        $decode_format = json_decode($res, TRUE);

        // 
        if (isset($decode_format['data'])) {
            // 
            if (isset($decode_format['data']['status'])) {
                // 
                if ($decode_format['data']['status'] == false) {
                    // 
                    $data_array = [
                        'status_code' => 420,
                        'message' => 'Failed due to invalid or missing postal code.'
                    ];
                }
            }

            // 
            if (isset($decode_format['data']['success'])) {
                // 
                if ($decode_format['data']['success'] == true) {
                    // 
                    foreach ($res2 as $value) {
                        // 
                        foreach ($decode_format['data']['pricing'] as $value2) {
                            // self::print_r($value2);
                            
                            // 
                            if ($value->code == $value2['courier_code']) {
                                // 
                                $courier = [
                                    'available' => $value->is_actived,
                                    'final_rate' => (int)$value2['price'],
                                    'insurance_amount' => '',
                                    'logo' => [
                                        'name' => $value->code,
                                        'src' => $value->logo
                                    ],
                                    'name' => $value2['courier_name'], 
                                    'rate_id' => $value2['courier_service_code'],
                                    'rate_name' => $value2['courier_name'],
                                    'rate_range' => $value2['shipment_duration_range'],
                                    'type' => $value2['courier_service_name']
                                ];
                                
                                // 
                                if ($value2['service_type'] == 'standard' && $value2['service_type'] == 'economy') {
                                    $data_array['regular'][] = $courier;
                                }
                                // 
                                elseif ($value2['service_type'] == 'overnight') {
                                    $data_array['express'][] = $courier;
                                }
                                // 
                                elseif ($value2['service_type'] == 'same_day') {
                                    $data_array['sameday'][] = $courier;
                                }
                            }
                        }
                    }
                }
            }   
        }

        // 
        return response()->json($data_array);
    }

    public function tracking_history(Request $request)
    {
        $request_input = $request->only('invoice_number');

        // self::print_r($order_res[0]->invoice_number);

        $res = self::nimbuspost_curl('https://id.nimbuspost.com/api/shipments/track_awb/'.$request_input['invoice_number'], '', false, 'get');
        
        // self::print_r($res);

        $decode_format = self::decode_format($res);

        if ($this->active == false) {
            if (isset($decode_format['data'])) {
                // 
                if (isset($decode_format['data']['status'])) {
                    // 
                    if ($decode_format['data']['status'] == true) {
                        foreach ($decode_format['data']['history'] as $value) {
                            $data_array[] = [
                                'date' => date('d-M-Y H:i:s', $value['event_time']),
                                'description' => $value['message'],
                            ];
                        }
                    }
                    else {

                    }
                }
            }
        }
        else {
            // 
            $data_array_example = self::example_result_tracking();

            // 
            if (isset($decode_format['data'])) {
                // 
                if (isset($decode_format['data']['status'])) {
                    // 
                    if ($decode_format['data']['status'] == true) {
                        foreach ($data_array_example['data']['history'] as $value) {
                            $data_array[] = [
                                'date' => date('d-M-Y H:i:s', $value['event_time']),
                                'description' => $value['message'],
                            ];
                        }
                    }
                }
            }
        }

        // 
        return response()->json($data_array);
    }

    public function create_new_shipment(Request $request)
    {
        // 
        $order_res = DB::table('orders')->get();

        // 
        foreach ($order_res as $value) {
            // 
            $jne_destination_res = DB::table('jne_destinations')->where('id', '=', $value->city_id)->get();
            
            // 
            foreach ($jne_destination_res as $value2) {
                // 
                $jne_destination_array = [
                    'city_name' => $value2->city_name,
                    'province_name' => $value2->province_name,
                    'zip_code' => $value2->zip_code,
                ];
            }

            // 
            $order_array = [
                'user_firstname' => $value->firstname,
                'user_address' => $value->address,
                'user_city' => $jne_destination_array['city_name'],
                'user_state' => $jne_destination_array['province_name'],
                'user_pincode' => $jne_destination_array['zip_code'],
                'user_phone' => (int)$value->phone,

                'invoice_number' => $value->invoice_number
            ];

            // 
            $order_item_res = DB::table('order_items')->where('order_id', '=', $value->id)->get();

            // 
            foreach ($order_item_res as $value3) {
                // 
                $product_res = DB::table('products')->where('id', '=', $value3->product_id)->get();

                // 
                foreach ($product_res as $value4) {
                    $items[] = [
                        // Required
                        'name' => $value4->nama,
                        'qty' => $value3->qty,
                        'price' => $value4->harga
                    ];
                }
            }
        }

        // 
        $post_array = [
            'consignee' => [
                'name' => $order_array['user_firstname'],
                'address' => $order_array['user_address'],
                'city' => $order_array['user_city'],
                'state' => $order_array['user_state'],
                'pincode' => $order_array['user_pincode'],
                'phone' => $order_array['user_phone']
            ],
            'order' => [
                'order_number' => $order_array['invoice_number'],
                'payment_type' => 'prepaid',
            ],
            'pickup_warehouse_id' => env('WAREHOUSE_ID'),
            'rto_warehouse_id' => env('WAREHOUSE_ID'),
            'order_items' => isset($items) ? $items : null
        ];
        
        // 
        $res = self::nimbuspost_curl('https://id.nimbuspost.com/api/shipments/create', $post_array, true, 'post');
        
        // 
        $decode_format = self::decode_format($res);
        
        if (isset($decode_format['data'])) {
            // 
            if (isset($decode_format['data']['status'])) {
                // 
                if ($decode_format['data']['status'] == true) {
                    // 
                    $orders_array = [
                        'resi_number' => $decode_format['data']['awb_number'],
                        'tracking_id' => $decode_format['data']['shipment_id'],
                    ];

                    // 
                    $order_res2 = DB::table('orders')->get();
                    
                    // 
                    foreach ($order_res2 as $value) {
                        $res_insert = DB::table('orders')->where('id', '=', $value->id)->update($orders_array);
                    }
                    
                    // 
                    if ($res_insert) {
                        $data_array = [
                            'status_code' => 210,
                            'message' => 'Successfully create new shipment.'
                        ];
                    }
                    else {
                        $data_array = [
                            'status_code' => 509,
                            'message' => 'Can\'t create new shipment. Please try again!'
                        ];
                    }
                }
                else {
                    
                }
            }
        }

        // 
        return response()->json($data_array);
    }

    public function create_request_pickup(Request $request)
    {
        // 
        $request_input = $request->only('invoice_number');

        // 
        $res = DB::table('orders')->where('invoice_number', '=', $request_input['invoice_number'])->get();

        // 
        $post_array = [
            'shipment_ids' => [(int)$res[0]->tracking_id]
        ];
        
        // 
        $res2 = self::nimbuspost_curl('https://id.nimbuspost.com/api/shipments/pickups', $post_array, true, 'post');
        
        // 
        $decode_format = self::decode_format($res2);
        
        // self::print_r($decode_format);
        
        // 
        if ($decode_format['status'] == true) {
            $data_array = [
                'status' => 210,
                'message' => $decode_format['message']
            ];
        }
        else {
            $data_array = [
                'status' => 509,
                'message' => $decode_format['message']
            ];
        }

        // 
        return response()->json($data_array); 
    }
    
    private static function nimbuspost_curl($url, $data = '', $curl_post, $type)
    {
        // self::print_r($url);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, $curl_post);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'NP-API-KEY: '.env('NP_API_KEY')
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data != '' ? json_encode($data) : '');
        curl_setopt($ch, CURLOPT_CAINFO, env('CAINFO_PATH'));
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            // echo "cURL Error #:" . $err;
        } else {
            return $response;
        }
    }

    private static function example_result_tracking()
    {
        $arrayVar = [
            "status" => true,
            "data" => [
                "awb_number" => "JO0174099872",
                "shipment_id" => "11033",
                "created" => "2023-04-03",
                "rto_awb" => "",
                "courier_name" => "J&T - Standard - EZ",
                "status" => "delivered",
                "rto_status" => "",
                "label" =>
                    "https://nimubs-assets.s3.amazonaws.com/nimbus-id/20230404142514-69.pdf",
                "tracking_url" =>
                    "https://id.nimbuspost.com/shipping/tracking/JO0174099872",
                "freight_charges" => "14000",
                "consignee details" => [
                    "name" => "Yoshe Salon",
                    "address" =>
                        "Desa Sugihwaras RT 14 / RW 04, Kec. Saradan, Kab. Madiun 63155, Saradan, Kab. Madiun, Jawa Timur",
                    "address_2" => "",
                    "city" => "Madiun",
                    "state" => "Jawa Timur",
                    "pincode" => "63155",
                    "phone" => "63155",
                ],
                "history" => [
                    [
                        "status_code" => "DL",
                        "location" => "",
                        "event_time" => "1680585240",
                        "message" => "Paket telah diterima",
                    ],
                    [
                        "status_code" => "OFD",
                        "location" => "",
                        "event_time" => "1680579240",
                        "message" => "Paket akan dikirim ke alamat penerima",
                    ],
                    [
                        "status_code" => "OFD",
                        "location" => "",
                        "event_time" => "1680575580",
                        "message" => "Paket telah sampai di MEJAYAN",
                    ],
                    [
                        "status_code" => "OFD",
                        "location" => "",
                        "event_time" => "1680564420",
                        "message" => "Paket akan dikirimkan ke MEJAYAN",
                    ],
                    [
                        "status_code" => "OFD",
                        "location" => "",
                        "event_time" => "1680564360",
                        "message" => "Paket telah sampai di MDN_GATEWAY",
                    ],
                    [
                        "status_code" => "OFD",
                        "location" => "",
                        "event_time" => "1680563760",
                        "message" => "Paket telah sampai di MDN_GATEWAY",
                    ],
                    [
                        "status_code" => "OFD",
                        "location" => "",
                        "event_time" => "1680547620",
                        "message" => "Paket akan dikirimkan ke MDN_GATEWAY",
                    ],
                    [
                        "status_code" => "OFD",
                        "location" => "",
                        "event_time" => "1680547140",
                        "message" => "Paket telah sampai di GSK2_GATEWAY",
                    ],
                    [
                        "status_code" => "OFD",
                        "location" => "",
                        "event_time" => "1680546840",
                        "message" => "Paket telah sampai di GSK2_GATEWAY",
                    ],
                    [
                        "status_code" => "OFD",
                        "location" => "",
                        "event_time" => "1680546540",
                        "message" => "Paket telah sampai di GSK2_GATEWAY",
                    ],
                    [
                        "status_code" => "OFD",
                        "location" => "",
                        "event_time" => "1680535380",
                        "message" => "Paket akan dikirimkan ke GSK2_GATEWAY",
                    ],
                    [
                        "status_code" => "OFD",
                        "location" => "",
                        "event_time" => "1680534600",
                        "message" => "Paket telah sampai di LMG_GATEWAY",
                    ],
                    [
                        "status_code" => "OFD",
                        "location" => "",
                        "event_time" => "1680519480",
                        "message" => "Paket akan dikirimkan ke LMG_GATEWAY",
                    ],
                    [
                        "status_code" => "N/A",
                        "location" => "",
                        "event_time" => "1680515760",
                        "message" => "Paket telah diterima oleh LAMONGAN_1",
                    ],
                    [
                        "status_code" => "PP",
                        "location" => "",
                        "event_time" => "1680507660",
                        "message" => "Manifes",
                    ],
                ],
            ],
        ];
        
        return $arrayVar;
    }
}
