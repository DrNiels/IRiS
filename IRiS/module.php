<?

include __DIR__ . "/../libs/WebHookModule.php";

class IRiS extends WebHookModule {

    public function __construct($InstanceID) {
        parent::__construct($InstanceID, "iris");
    }
    
    public function Create(){
        //Never delete this line!
        parent::Create();
        
        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        $this->RegisterPropertyString("Address", "");
        $this->RegisterPropertyString("BuildingMaterial", "");
        $this->RegisterPropertyString("HeatingType", "");

        $this->RegisterPropertyString("Floors", "[]");
        $this->RegisterPropertyString("Rooms", "[]");
        $this->RegisterPropertyString("SmokeDetectors", "[]");
        
    }

    public function Destroy(){
        //Never delete this line!
        parent::Destroy();
        
    }

    public function ApplyChanges(){
        //Never delete this line!
        parent::ApplyChanges();
    }
    
    public function GetConfigurationForm() {
        $cmpFloors = function($first, $second) {
            return ($first->level > $second->level) ? -1 : 1;
        };
        $floors = json_decode($this->ReadPropertyString('Floors'));
        usort($floors, $cmpFloors);
        $floorOptions = [];
        foreach ($floors as $floor) {
            $floorOptions[] = [
                'label' => $floor->name,
                'value' => $floor->id
            ];
        }
        return json_encode([
            'elements' => [
                [
                    'type' => 'ValidationTextBox',
                    'name' => 'Address',
                    'caption' => 'Address'
                ],
                [
                    'type' => 'ValidationTextBox',
                    'name' => 'BuildingMaterial',
                    'caption' => 'Building Material'
                ],
                [
                    'type' => 'ValidationTextBox',
                    'name' => 'HeatingType',
                    'caption' => 'Heating Type'
                ],
                [
                    'type' => 'List',
                    'name' => 'Floors',
                    'caption' => 'Floors',
                    'add' => true,
                    'delete' => true,
                    'sort' => [
                        'column' => 'level',
                        'direction' => 'descending'
                    ],
                    'columns' => [
                        [
                            'label' => 'ID',
                            'name' => 'id',
                            'width' => '75px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'NumberSpinner'
                            ]
                        ],
                        [
                            'label' => 'Level',
                            'name' => 'level',
                            'width' => '100px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'NumberSpinner'
                            ]
                        ],
                        [
                            'label' => 'Name',
                            'name' => 'name',
                            'width' => 'auto',
                            'add' => '',
                            'edit' => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'label' => 'Type',
                            'name' => 'type',
                            'width' => '100px',
                            'add' => '',
                            'edit' => [
                                'type' => 'ValidationTextBox'
                            ]
                        ]

                    ],
                    'values' => []
                ],
                [
                    'type' => 'List',
                    'name' => 'Rooms',
                    'caption' => 'Rooms',
                    'add' => true,
                    'delete' => true,
                    'sort' => [
                        'column' => 'floor',
                        'direction' => 'descending'
                    ],
                    'columns' => [
                        [
                            'label' => 'ID',
                            'name' => 'id',
                            'width' => '75px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'NumberSpinner'
                            ]
                        ],
                        [
                            'label' => 'Floor',
                            'name' => 'floor',
                            'width' => '200px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'Select',
                                'options' => $floorOptions
                            ]
                        ],
                        [
                            'label' => 'Name',
                            'name' => 'name',
                            'width' => 'auto',
                            'add' => '',
                            'edit' => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'label' => 'Type',
                            'name' => 'type',
                            'width' => '200px',
                            'add' => '',
                            'edit' => [
                                'type' => 'ValidationTextBox'
                            ]
                        ]
                    ],
                    'values' => []
                ],
                [
                    'type' => 'List',
                    'name' => 'SmokeDetectors',
                    'caption' => 'Smoke Detectors',
                    'add' => true,
                    'delete' => true,
                    'columns' => [
                        [
                            'label' => 'ID',
                            'name' => 'id',
                            'width' => '75px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'NumberSpinner'
                            ]
                        ],
                        [
                            'label' => 'Room',
                            'name' => 'room',
                            'width' => '200px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'NumberSpinner'
                            ]
                        ],
                        [
                            'label' => 'Variable',
                            'name' => 'variableID',
                            'width' => 'auto',
                            'add' => 0,
                            'edit' => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'label' => 'Map Position X',
                            'name' => 'x',
                            'width' => '130px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'NumberSpinner'
                            ]
                        ],
                        [
                            'label' => 'Map Position Y',
                            'name' => 'y',
                            'width' => '130px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'NumberSpinner'
                            ]
                        ]
                    ],
                    'values' => []
                ]
            ]
        ]);
    }

    /**
     * This function will be called by the hook control. Visibility should be protected!
     */
    protected function ProcessHookData() {
        //Never delete this line!
        parent::ProcessHookData();

        // The raw data contains the body that describes the RPC call
        $requestString = file_get_contents("php://input");
        $this->SendDebug("IRiS - Request", $requestString, 0);
        $request = json_decode($requestString, true);
        if (!isset($request['id']) || !isset($request['method'])) {
            // TODO: Handle error
            $this->SendDebug("IRiS - Error", "Invalid request", 0);
            return;
        }

        switch ($request['method']) {
            case 'getGeneralInformation':
                $this->ReturnResult($request['id'], [
                    'address' => $this->ReadPropertyString('Address'),
                    'buildingMaterial' => $this->ReadPropertyString('BuildingMaterial'),
                    'heatingType' => $this->ReadPropertyString('HeatingType')
                ]);
                break;

            case 'getObjectList':
                $this->ReturnResult($request['id'], [
                    'persons' => [], // TODO: Fill persons
                    'floors' => json_decode($this->ReadPropertyString('Floors'), true),
                    'rooms' => json_decode($this->ReadPropertyString('Rooms'), true),
                    'devices' => $this->GetObjectListDevices()
                ]);
                break;

            case 'getStatus':
                $ids = [];
                if (isset($request['params']['ids'])) {
                    $ids = $request['params']['ids'];
                }
                $this->ReturnResult($request['id'], $this->ComputeStatus($ids));
                break;

            default:
                $this->SendDebug("IRiS - Error", "Undefined method", 0);

        }
    }

    private function ReturnResult($id, $result) {
        $response = json_encode([
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id
        ]);
        $this->SendDebug("IRiS - Response", $response, 0);
        echo $response;
    }

    private function GetObjectListDevices() {
        $result = [];

        foreach (json_decode($this->ReadPropertyString('SmokeDetectors'), true) as $smokeDetector) {
            $result[] = $this->ComputeDeviceInformation($smokeDetector, 'SmokeDetector');
        }

        return $result;
    }

    private function ComputeDeviceInformation($value, $type) {
        return [
            'id' => $value['id'],
            'room' => $value['room'],
            'position' => [
                'x' => $value['x'],
                'y' => $value['y']
            ],
            'type' => $type
        ];
    }

    private function ComputeStatus($ids) {
        $persons = []; // TODO: Fill persons

        $rooms = [];
        foreach (json_decode($this->ReadPropertyString('Rooms'), true) as $room) {
            if ((sizeof($ids) == 0) || in_array($room['id'], $ids)) {
                $rooms[] = [
                    'id' => $room['id'],
                    'status' => 'fine' // TODO: Determine current state
                ];
            }
        }

        $devices = [];
        foreach (json_decode($this->ReadPropertyString('SmokeDetectors'), true) as $smokeDetector) {
            if ((sizeof($ids) == 0) || in_array($smokeDetector['id'], $ids)) {
                $smokeValue = $this->GetPercentageValue($smokeDetector['variableID']);
                if ($smokeValue !== null) {
                    $devices[] = [
                        'id' => $smokeDetector['id'],
                        'value' => [
                            'smoke' => $smokeValue
                        ]
                    ];
                }
            }
        }

        return [
            'persons' => $persons,
            'rooms' => $rooms,
            'devices' => $devices
        ];
    }

    private function GetPercentageValue($variableID)
    {
        if (!IPS_VariableExists($variableID)) {
            return null;
        }
        $targetVariable = IPS_GetVariable($variableID);

        if ($targetVariable['VariableCustomProfile'] != '') {
            $profileName = $targetVariable['VariableCustomProfile'];
        } else {
            $profileName = $targetVariable['VariableProfile'];
        }

        $profile = IPS_GetVariableProfile($profileName);

        if (($profile['MaxValue'] - $profile['MinValue']) <= 0) {
            return null;
        }

        $valueToPercent = function ($value) use ($profile) {
            return (($value - $profile['MinValue']) / ($profile['MaxValue'] - $profile['MinValue']));
        };

        $value = $valueToPercent(GetValue($variableID));

        // Revert value for reversed profile
        if (preg_match('/\.Reversed$/', $profileName)) {
            $value = 1 - $value;
        }

        return max(min($value, 1), 0);
    }
}

?>