<?php

class crud_user extends CI_Controller
{

    function __construct()
    {
        parent::__construct();
        $this->load->model('m_update','m_insert');
        $this->load->library(['session', 'ion_auth', 'form_validation', 'general']);
        $this->output->set_header('Last-Modified: ' . gmdate("D, d M Y H:i:s") . ' GMT');
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
		$this->output->set_header('Pragma: no-cache');
		$this->output->set_header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        if ( !$this->ion_auth->logged_in() || !$this->ion_auth->is_admin() || $this->ion_auth->user()->result()[0]->type > 4 )
    		die();
    }

    public function deleteUser()
    {
        $InputData = json_decode(file_get_contents('php://input'),true);
        $UserId = $InputData['UserId'];
        $Return['StatusResponse'] = 0;
        if ($this->ion_auth->delete_user($UserId)) {
            $Return['StatusResponse'] = 1;
        }
        echo json_encode($Return);
    }

    public function acceptSeller(){
        $InputData = json_decode(file_get_contents('php://input'),true);
        $UserId = $InputData['UserId'];
        $Return['StatusResponse'] = 0;
        $dataUpdate = [
            'table' => 'users_request',
            'where' => ['user_id' => $UserId],
            'data'  => ['seller_status' => 'on process']
        ];
        if ($this->m_update->updateDynamic($dataUpdate)) {
            $Return['StatusResponse'] = 1;
        }
        echo json_encode($Return);
        
    }

    public function rejectSeller(){
        $InputData = json_decode(file_get_contents('php://input'),true);
        $UserId = $InputData['UserId'];
        $Return['StatusResponse'] = 0;
        $dataUpdate = [
            'table' => 'users_request',
            'where' => ['user_id' => $UserId],
            'data'  => ['seller_status' => 'rejected']
        ];
        if ($this->m_update->updateDynamic($dataUpdate)) {
            $Return['StatusResponse'] = 1;
        }
        echo json_encode($Return);
    }

    public function acceptBuyer(){
        $InputData = json_decode(file_get_contents('php://input'),true);
        $UserId = $InputData['UserId'];
        $Return['StatusResponse'] = 0;

        //Submit to PrivyId
        $privy = $this->db->query('select * from privyid_api')->row();
        $doc = $this->db->query('select scan_ktp, scan_selfie from users_document where user_id ="'.$UserId.'"')->row();
        $url = $privy->base.$privy->reg;
        $data = [
            'auth' => [$privy->user,$privy->pass],
            'headers' => [
                'Merchant-Key' => $privy->merchant_key
            ],
            'multipart' => [
                [
                    'name' => 'selfie',
                    'contents' => fopen('./assets/file_upload/'.$doc->scan_selfie, 'r')
                ],
                [
                    'name' => 'ktp',
                    'contents' => fopen('./assets/file_upload/'.$doc->scan_ktp, 'r')
                ],
                [
                    'name' => 'email',
                    'contents' => $InputData['Email'],
                ],
                [
                    'name' => 'phone',
                    'contents' => $InputData['Phone'],
                ],
                [
                    'name' => 'identity',
                    'contents' => json_encode(
                        [
                            'nik' => $InputData['Nik'],
                            'nama' => $InputData['FirstName'].' '.$InputData['LastName']
                        ]
                    )
                ]
            ]  
        ];
        
        $this->load->library('privyid_api');
        $resp = $this->privyid_api->postPrivyAPI($url, $data);
        $r = json_decode($resp);
        // echo $r->code;die();
        echo $resp;
        
        if($r->code == 201){

            $dataUpdate = [
                'table' => 'users_request',
                'where' => ['user_id' => $UserId],
                'data'  => ['buyer_status' => 'on process']
            ];

            $this->m_update->updateDynamic($dataUpdate);
        
            $dataUser = [
                'email' => $r->data->email,
                'phone' => $r->data->phone,
                'token' => $r->data->userToken,
                'status' => $r->data->status,
                'created_date' => date('Y-m-d H:i:s'),
                'user_id' => $user->id
            ];

            $dataInsert = [
                'table' => 'users_privyid',
                'data' => $dataUser
            ];
            $this->m_insert->insertDynamic($dataInsert);

            $Return['StatusResponse'] = 1;
        }
        
        echo json_encode($Return);
    }

    public function rejectBuyer(){
        $InputData = json_decode(file_get_contents('php://input'),true);
        $UserId = $InputData['UserId'];
        $Return['StatusResponse'] = 0;
        $dataUpdate = [
            'table' => 'users_request',
            'where' => ['user_id' => $UserId],
            'data'  => ['buyer_status' => 'rejected']
        ];
        if ($this->m_update->updateDynamic($dataUpdate)) {
            $Return['StatusResponse'] = 1;
        }
        echo json_encode($Return);
    }

    public function submitDokumen(){
        $InputData = json_decode(file_get_contents('php://input'),true);
        $Return['StatusResponse'] = 0;

        $privy = $this->db->query('select * from users_privyid')->row();
        $url = $privy->privy.$api->doc_upload;
        $data = [
            'auth' => [$privy->user,$privy->pass],
            'headers' => [
                'Merchant-Key' => $privy->merchant_key,
            ],
            'query' => [
                'documentTitle' => 'doc title',
                'docType' => 'serial or parallel',
                'owner' => [
                    'privyId' => 'a privy id',
                    'enterpriseToken' => 'example token'
                ],
                'recipients' => [
                    [
                        'privyId' => 'a privy id',
                        'type' => 'Signer',
                        'enterpriseToken' => 'companyToken',
                    ],
                    [
                        'privyId' => 'a privy id',
                        'type' => 'Reviewer',
                        'enterpriseToken' => '',
                    ]
                ]
            ],
            'multipart' => [
                [
                    'name' => 'documentTitle',
                    'contents' => $InputData['name']
                ],
                [
                    'name' => 'docType',
                    'contents' => 'Serial'
                ],
                [
                    'name' => 'owner',
                    'contents' => json_encode([
                        'privyId' => $InputData['owner']['privyId'],
                        'enterpriseToken' => $InputData['owner']['enterpriseToken']
                    ])
                ],
                [
                    'name' => 'document',
                    'contents' => fopen('./assets/file_upload/'.$InputData['name'], 'r')
                ],
                [
                    'name' => 'recipients',
                    'contents' => json_encode([
                            [
                                'privyId' => '',
                                'type' => '',
                                'enterpriseToken' => ''
                            ],
                            [
                                'privyId' => '',
                                'type' => '',
                                'enterpriseToken' => ''
                            ]
                        ])
                ],

            ]

        ];
                
        $this->load->library('privyid_api');
        $resp = $this->privyid_api->postPrivyAPI($url, $data);
        $r = json_decode($resp, true);

        if ($r->code == 201) {
            $Return['StatusResponse'] = 1;
        }
        echo json_encode($Return);
    }


    public function editUser()
    {
        $InputData = json_decode(file_get_contents('php://input'),true);
        $UserData = $this->general->filtering_datas($InputData['data']);
        $Return['StatusResponse'] = 0;

        $UserName = '';
        if ($UserData['Group'] == 1) {
            $UserName = $UserData['UserName'];
        }
        else if ($UserData['Group'] == 2) {
            $UserName = $UserData['UserName'];
        }

        // --- Edit Password --- //
        if (isset($UserData['Password1']) && isset($UserData['Password2']) ) {
            if ($UserData['Password1'] != $UserData['Password2']) {
                $Return['Message'] = 'Password Not match';
                echo json_encode($Return);
                die();
            }
            else {
                $user = $this->ion_auth->user($UserData['MemberId'])->row();
                $Password = $this->ion_auth->reset_password($user->email, $UserData['Password1']);
                $Return['user'] = $user;
            }
        }
        else {
            $Password = false;
        }
         // --- End Password --- //

        $dataUser = array(
            'first_name'    => $UserData['FirstName'],
            'last_name'     => $UserData['LastName'],
            'username'      => $UserName,
            'updated_date'  => date('Y-m-d H:i:s'),
            'phone'         => $UserData['Phone']
         );

        if ($this->ion_auth->update($UserData['MemberId'], $dataUser))
        {
            $this->ion_auth->set_message_delimiters('<p><strong>','</strong></p>');
            $messages = $this->ion_auth->messages();
            $Return['StatusResponse'] = 1;
        }
        else
        {
            $this->ion_auth->set_error_delimiters('<p><strong>','</strong></p>');
            $errors = $this->ion_auth->errors();
            $Return['Message'] = $errors;
        }

        $Return['UserData'] = $UserData;
        $Return['Message']  = '';
        echo json_encode($Return);
    }

    // -------------------------------------------------- BANK -------------------------------------------------- //
    function addUser()
    {
        $InputData = json_decode(file_get_contents('php://input'),true);
        $UserData = $this->general->filtering_datas($InputData['data']);
        $Return = ['StatusResponse'=>0, 'Message'=>'', 'Data'=> $UserData];
        
        
        if ($UserData['Password1'] != $UserData['Password2']) {
            $Return['Message'] = 'Password Not match';
            echo json_encode($Return);
            die();
        }

        $AdditionalData = array(
            'first_name'    => $UserData['FirstName'],
            'last_name'     => $UserData['LastName'],
            'phone'         => $UserData['Phone'],
            'status'        => 1,
            'type'          => $UserData['Group'] == 1 ? 2 : 5,
            'created_date'  => date('Y-m-d H:i:s'),
            'created_by'    => 0
        );
    
        $this->ion_auth->register($UserData['UserName'], $UserData['Password1'], $UserData['Email'], $AdditionalData, [$UserData['Group']]);

        $Return['StatusResponse'] = 1;
        echo json_encode($Return);
    }

}