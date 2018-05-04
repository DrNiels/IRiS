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
        $this->RegisterPropertyString("HeatingType", "{}");
        $this->RegisterPropertyString("AlarmTypes", "[]");

        $this->RegisterPropertyString("Floors", "[]");
        $this->RegisterPropertyString("Rooms", "[]");
        $this->RegisterPropertyString("Persons", "[]");
        $this->RegisterPropertyString("SmokeDetectors", "[]");
        $this->RegisterPropertyString("TemperatureSensors", "[]");
        $this->RegisterPropertyString("MotionSensors", "[]");
        $this->RegisterPropertyString("Doors", "[]");
        
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
                    'type' => 'List',
                    'name' => 'HeatingType',
                    'columns' => [
                        [
                            'caption' => 'Heating Type',
                            'name' => 'heatingType',
                            'width' => '150px'
                        ],
                        [
                            'caption' => '',
                            'name' => 'selected',
                            'width' => '50px',
                            'edit' => [
                                'type' => 'CheckBox'
                            ]
                        ]
                    ],
                    'values' => [
                        [
                            'heatingType' => $this->Translate('Electric'),
                            'selected' => false
                        ],
                        [
                            'heatingType' => $this->Translate('Oil'),
                            'selected' => false
                        ],
                        [
                            'heatingType' => $this->Translate('Gas'),
                            'selected' => false
                        ],
                        [
                            'heatingType' => $this->Translate('Thermal'),
                            'selected' => false
                        ],
                        [
                            'heatingType' => $this->Translate('Pellets'),
                            'selected' => false
                        ],
                        [
                            'heatingType' => $this->Translate('Solar'),
                            'selected' => false
                        ]
                    ]
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
                        ],
                        [
                            'label' => 'Map',
                            'name' => 'map',
                            'width' => '100px',
                            'add' => '',
                            'edit' => [
                                'type' => 'SelectFile'
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
                    'name' => 'Persons',
                    'caption' => 'Persons (with Testing options)',
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
                            'label' => 'Name',
                            'name' => 'name',
                            'width' => 'auto',
                            'add' => '',
                            'edit' => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'label' => 'Birthday',
                            'name' => 'birthday',
                            'width' => '200px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'NumberSpinner'
                            ]
                        ],
                        [
                            'label' => 'Diseases',
                            'name' => 'diseases',
                            'width' => '250px',
                            'add' => '',
                            'edit' => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'label' => 'Core Data',
                            'name' => 'coreData',
                            'width' => '100px',
                            'add' => true,
                            'edit' => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'label' => 'Present?',
                            'name' => 'present',
                            'width' => '200px',
                            'add' => 'Unknown',
                            'edit' => [
                                'type' => 'Select',
                                'options' => [
                                    [
                                        'label' => 'Present',
                                        'value' => 'Present'
                                    ],
                                    [
                                        'label' => 'Not Present',
                                        'value' => 'NotPresent'
                                    ],
                                    [
                                        'label' => 'Unknown',
                                        'value' => 'Unknown'
                                    ]
                                ]
                            ]
                        ],
                        [
                            'label' => 'Current Location (Room ID)',
                            'name' => 'currentLocation',
                            'width' => '250px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'NumberSpinner'
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
                ],
                [
                    'type' => 'List',
                    'name' => 'TemperatureSensors',
                    'caption' => 'Temperature Sensors',
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
                ],
                [
                    'type' => 'List',
                    'name' => 'MotionSensors',
                    'caption' => 'Motion Sensors',
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
                ],
                [
                    'type' => 'List',
                    'name' => 'Doors',
                    'caption' => 'Doors',
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

    public function ChangePositionOfPerson($personID, $newRoomPosition) {
        $persons = json_decode($this->ReadPropertyString('Persons'), true);

        for ($i = 0; $i < sizeof($persons); $i++) {
            if ($persons[$i]['id'] == $personID) {
                $persons[$i]['currentLocation'] = $newRoomPosition;
                break;
            }
        }

        IPS_SetProperty($this->InstanceID, 'Persons', json_encode($persons));
        IPS_ApplyChanges($this->InstanceID);
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
            case 'isAlarm':
                $this->ReturnResult($request['id'], $this->ComputeAlarm());
                break;

            case 'getGeneralInformation':
                $this->ReturnResult($request['id'], [
                    'address' => $this->ReadPropertyString('Address'),
                    'buildingMaterial' => $this->ReadPropertyString('BuildingMaterial'),
                    'heatingType' => $this->ComputeHeatingType()
                ]);
                break;

            case 'getObjectList':
                $this->ReturnResult($request['id'], [
                    'persons' => $this->GetObjectListPersons(),
                    'floors' => $this->GetObjectListFloors(),
                    'rooms' => json_decode($this->ReadPropertyString('Rooms'), true),
                    'devices' => $this->GetObjectListDevices()
                ]);
                break;

            case 'getMaps':
                $this->ReturnResult($request['id'], $this->ComputeMaps());
                break;

            case 'getStatus':
                $ids = [];
                if (isset($request['params']['ids'])) {
                    $ids = $request['params']['ids'];
                }
                $this->ReturnResult($request['id'], $this->ComputeStatus($ids));
                break;

            case 'switchDevice':
                $this->ReturnResult($request['id'], $this->SwitchVariable($request['params']['id'], $request['params']['value']));
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

    private function GetObjectListPersons() {
        $result = [];

        foreach (json_decode($this->ReadPropertyString('Persons'), true) as $person) {
            unset($person['currentLocation']);
            unset($person['present']);

            if ($person['name'] == '') {
                unset($person['name']);
            }

            if ($person['birthday'] == 0) {
                unset($person['birthday']);
            }

            if ($person['diseases'] == '') {
                unset($person['diseases']);
            }
            else {
                $person['diseases'] = explode(',', $person['diseases']);
            }

            $result[] = $person;
        }

        return $result;

    }

    private function GetObjectListFloors() {
        $result = [];

        foreach (json_decode($this->ReadPropertyString('Floors'), true) as $floor) {
            unset($floor['map']);
            $result[] = $floor;
        }

        return $result;
    }

    private function GetObjectListDevices() {
        $result = [];

        foreach (json_decode($this->ReadPropertyString('SmokeDetectors'), true) as $smokeDetector) {
            $result[] = $this->ComputeDeviceInformation($smokeDetector, 'SmokeDetector', false);
        }

        foreach (json_decode($this->ReadPropertyString('MotionSensors'), true) as $motionSensor) {
            $result[] = $this->ComputeDeviceInformation($motionSensor, 'MotionSensor', false);
        }

        foreach (json_decode($this->ReadPropertyString('TemperatureSensors'), true) as $temperatureSensor) {
            $result[] = $this->ComputeDeviceInformation($temperatureSensor, 'TemperatureSensor', false);
        }

        foreach (json_decode($this->ReadPropertyString('Doors'), true) as $door) {
            $variable = IPS_GetVariable($door['variableID']);
            $switchable = ($variable['VariableCustomAction'] > 10000) || ($variable['VariableAction'] > 10000);
            $result[] = $this->ComputeDeviceInformation($door, 'Door', $switchable);
        }

        return $result;
    }

    private function ComputeHeatingType() {
        $heatingTypes = json_decode($this->ReadPropertyString('HeatingType'), true);
        $typeLabels = [ 'Electric', 'Oil', 'Gas', 'Thermal', 'Pellets', 'Solar'];
        $returnValue = [];

        for ($i = 0; $i < sizeof($heatingTypes); $i++) {
            if ($heatingTypes[$i]['selected']) {
                $returnValue[] = $typeLabels[$i];
            }
        }

        return $returnValue;
    }

    private function ComputeDeviceInformation($value, $type, $switchable) {
        return [
            'id' => $value['id'],
            'room' => $value['room'],
            'position' => [
                'x' => $value['x'],
                'y' => $value['y']
            ],
            'type' => $type,
            'switchable' => $switchable
        ];
    }

    private function ComputeMaps() {
        $maps = [];
        foreach (json_decode($this->ReadPropertyString('Floors'), true) as $floor) {
            $maps[] = [
                'floor' => $floor['id'],
                'map' => $floor['map']
            ];
        }

        return $maps;
    }

    private function ComputeStatus($ids) {
        $persons = [];
        foreach (json_decode($this->ReadPropertyString('Persons'), true) as $person) {
            if ((sizeof($ids) == 0) || in_array($person['id'], $ids)) {
                $newEntry = [
                    'id' => $person['id'],
                    'present' => $person['present']
                ];
                if ($person['present'] == 'Present') {
                    $newEntry['likelyPositions'] = [
                        [
                            'room' => $person['currentLocation'],
                            'probability' => 0.9
                        ]
                    ];
                }

                $persons[] = $newEntry;
            }
        }

        $rooms = [];
        foreach (json_decode($this->ReadPropertyString('Rooms'), true) as $room) {
            if ((sizeof($ids) == 0) || in_array($room['id'], $ids)) {
                $rooms[] = [
                    'id' => $room['id'],
                    'status' => $this->ComputeStatusOfRoom($room['id'])
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

        foreach (json_decode($this->ReadPropertyString('TemperatureSensors'), true) as $temperatureSensor) {
            if (((sizeof($ids) == 0) || in_array($temperatureSensor['id'], $ids)) && IPS_VariableExists($temperatureSensor['variableID'])) {
                $devices[] = [
                    'id' => $temperatureSensor['id'],
                    'value' => [
                        'temperature' => GetValue($temperatureSensor['variableID'])
                    ]
                ];
            }
        }

        foreach (json_decode($this->ReadPropertyString('Doors'), true) as $door) {
            if ((sizeof($ids) == 0) || in_array($door['id'], $ids)) {
                $devices[] = [
                    'id' => $door['id'],
                    'value' => [
                        'open' => GetValueBoolean($door['variableID'])
                    ]
                ];
            }
        }

        foreach (json_decode($this->ReadPropertyString('MotionSensors'), true) as $motionSensor) {
            if ((sizeof($ids) == 0) || in_array($motionSensor['id'], $ids)) {
                $devices[] = [
                    'id' => $motionSensor['id'],
                    'value' => [
                        'motionDetected' => GetValueBoolean($motionSensor['variableID']),
                        'lastUpdate' => IPS_GetVariable($motionSensor['variableID'])['VariableUpdated']
                    ]
                ];
            }
        }

        return [
            'persons' => $persons,
            'rooms' => $rooms,
            'devices' => $devices
        ];
    }

    private function ComputeStatusOfRoom($roomID) {
        $status = [];

        $smokeValues = [];

        foreach (json_decode($this->ReadPropertyString('SmokeDetectors'), true) as $smokeDetector) {
            if ($smokeDetector['room'] == $roomID) {
                $smokeValues[] = $this->GetPercentageValue($smokeDetector['variableID']);
            }
        }

        $averageSmoke = 0;
        foreach ($smokeValues as $smokeValue) {
            $averageSmoke += $smokeValue;
        }

        if (sizeof($smokeValues) > 0) {
            $averageSmoke /= sizeOf($smokeValues);
        }

        if ($averageSmoke > 0.5) {
            $status[] = 'Smoked';
        }

        $temperatureValues = [];

        foreach (json_decode($this->ReadPropertyString('TemperatureSensors'), true) as $temperatureSensor) {
            if (($temperatureSensor['room'] == $roomID) && IPS_VariableExists($temperatureSensor['variableID'])) {
                $temperatureValues[] = GetValue($temperatureSensor['variableID']);
            }
        }

        $maxTemperature = 0;
        foreach ($temperatureValues as $temperatureValue) {
            if ($temperatureValue > $maxTemperature) {
                $maxTemperature = $temperatureValue;
            }
        }

        if ($maxTemperature > 100) {
            $status[] = 'Burning';
        }

        return $status;
    }

    private function ComputeAlarm() {
        $alarmTypes = json_decode($this->ReadPropertyString('AlarmTypes'));
        if (!in_array('Fire', $alarmTypes)) {
            foreach (json_decode($this->ReadPropertyString('Rooms'), true) as $room) {
                $roomStatus = $this->ComputeStatusOfRoom($room['id']);
                if (in_array('Smoked', $roomStatus) || in_array('Burning', $roomStatus)) {
                    $alarmTypes[] = 'Fire';
                    break;
                }
            }
        }

        IPS_SetProperty($this->InstanceID, 'AlarmTypes', json_encode($alarmTypes));
        IPS_ApplyChanges($this->InstanceID);

        $result = [
            'alarm' => (sizeof($alarmTypes) > 0)
        ];
        if (sizeof($alarmTypes) > 0) {
            $result['types'] = $alarmTypes;
        }
        return $result;
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

    private function SwitchVariable($irisID, $value) {
        $variableID = $this->GetVariableIDByIRISID($irisID);

        if (!IPS_VariableExists($variableID)) {
            return false;
        }

        $targetVariable = IPS_GetVariable($variableID);

        if ($targetVariable['VariableCustomAction'] != 0) {
            $profileAction = $targetVariable['VariableCustomAction'];
        } else {
            $profileAction = $targetVariable['VariableAction'];
        }

        if ($profileAction < 10000) {
            return false;
        }

        if (IPS_InstanceExists($profileAction)) {
            IPS_RunScriptText('IPS_RequestAction(' . var_export($profileAction, true) . ', ' . var_export(IPS_GetObject($variableID)['ObjectIdent'], true) . ', ' . var_export($value, true) . ');');
        } elseif (IPS_ScriptExists($profileAction)) {
            IPS_RunScriptEx($profileAction, ['VARIABLE' => $variableID, 'VALUE' => $value, 'SENDER' => 'IRiS']);
        } else {
            return false;
        }

        return true;
    }

    private function GetVariableIDByIRISID($irisID) {
        foreach (["SmokeDetectors", "MotionSensors", "Doors"] as $property) {
            foreach (json_decode($this->ReadPropertyString($property), true) as $value) {
                if ($value['id'] == $irisID) {
                    return $value['variableID'];
                }
            }
        }

        $this->SendDebug("IRiS - Error", "Variable for IRiS ID does not exist", 0);
        return 0;
    }
}

?>