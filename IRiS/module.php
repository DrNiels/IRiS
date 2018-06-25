<?

include __DIR__ . "/../libs/WebHookModule.php";

class IRiS extends WebHookModule {

    const INITIAL_PROBABILITY_MOTION = 0.8;
    const DECAY_PROBABILITY_MOTION = 90; // Subtract probability by 0.01 every 90 seconds if there is no motion trigger = Completely removed after 2 hours
    const SMOKE_DETECTOR_TURNBACK = 30; // If a motion sensors triggers 30 seconds before a smoke detector, undo the position update
    const STORED_PREVIOUS_DATA = 10; // Store the last 10 datasets of room + confirmation time

    public function __construct($InstanceID) {
        parent::__construct($InstanceID, "iris");
    }
    
    public function Create(){
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString("Address", "");
        $this->RegisterPropertyString("BuildingMaterial", "");
        $this->RegisterPropertyString("HeatingType", "{}");
        $this->RegisterPropertyString("AlarmTypes", "[]");
        $this->RegisterPropertyInteger("PersonDetectionMode", 0);

        $this->RegisterPropertyString("Floors", "[]");
        $this->RegisterPropertyString("Rooms", "[]");
        $this->RegisterPropertyString("RoomConnections", "[]");
        $this->RegisterPropertyString("Persons", "[]");
        $this->RegisterPropertyString("SmokeDetectors", "[]");
        $this->RegisterPropertyString("TemperatureSensors", "[]");
        $this->RegisterPropertyString("MotionSensors", "[]");
        $this->RegisterPropertyString("Doors", "[]");
        $this->RegisterPropertyString("Actors", "[]");

        $variableID = $this->RegisterVariableString("DetectedPersons", $this->Translate("Detected Persons"), "", 0);

        if (GetValueString($variableID) == '') {
            SetValueString($variableID, '{}');
        }
    }

    public function Destroy(){
        //Never delete this line!
        parent::Destroy();
        
    }

    public function ApplyChanges(){
        //Never delete this line!
        parent::ApplyChanges();

        foreach (json_decode($this->ReadPropertyString('MotionSensors'), true) as $motionSensor) {
            $this->RegisterMessage($motionSensor['variableID'], 10603 /* VM_UPDATE */);
        }

        foreach (json_decode($this->ReadPropertyString('SmokeDetectors'), true) as $smokeDetector) {
            $this->RegisterMessage($smokeDetector['variableID'], 10603 /* VM_UPDATE */);
        }

        foreach (json_decode($this->ReadPropertyString('Actors'), true) as $actor) {
            $this->RegisterMessage($actor['variableID'], 10603 /* VM_UPDATE */);
        }

        $this->FillIDs();
    }
    
    public function GetConfigurationForm() {
        $cmpFloors = function($first, $second) {
            return ($first['level'] > $second['level']) ? -1 : 1;
        };
        $floors = json_decode($this->ReadPropertyString('Floors'), true);
        usort($floors, $cmpFloors);
        $floorOptions = [];
        foreach ($floors as $floor) {
            $floorOptions[] = [
                'caption' => $floor['name'],
                'value' => intval($floor['id'])
            ];
        }
        $floorAdd = 0;
        if (sizeof($floorOptions) > 0) {
            $floorAdd = $floorOptions[0]['value'];
        }

        $cmpRooms = function($first, $second) use ($floors) {
            $firstLevel = 0;
            $secondLevel = 0;
            foreach ($floors as $floor) {
                if (intval($floor['id']) == $first['floor']) {
                    $firstLevel = $floor['level'];
                }
                if (intval($floor['id']) == $second['floor']) {
                    $secondLevel = $floor['level'];
                }
            }
            if ($firstLevel > $secondLevel) {
                return -1;
            }
            elseif ($firstLevel < $secondLevel) {
                return 1;
            }
            else {
                return strcmp($first['name'], $second['name']);
            }
        };
        $rooms = json_decode($this->ReadPropertyString('Rooms'), true);
        usort($rooms, $cmpRooms);
        $roomOptions = [];
        foreach ($rooms as $room) {
            $roomOptions[] = [
                'caption' => $room['name'],
                'value' => intval($room['id'])
            ];
        }
        $roomAdd = 0;
        if (sizeof($roomOptions) > 0) {
            $roomAdd = $roomOptions[0]['value'];
        }

        return json_encode([
            'elements' => [
                [
                    'type' => 'ValidationTextBox',
                    'name' => 'Address',
                    'caption' => 'Address'
                ],
                [
                    'type' => 'Select',
                    'name' => 'BuildingMaterial',
                    'caption' => 'Building Material',
                    'options' => [
                        [
                            'caption' => 'Stone',
                            'value' => 'Stone'
                        ],
                        [
                            'caption' => 'Timber',
                            'value' => 'Timber'
                        ],
                        [
                            'caption' => 'Loam and Straw',
                            'value' => 'LoamStraw'
                        ],
                        [
                            'caption' => 'Steel',
                            'value' => 'Steel'
                        ]
                    ]
                ],
                [
                    'type' => 'List',
                    'name' => 'HeatingType',
                    'rowCount' => 6,
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
                    'rowCount' => 5,
                    'caption' => 'Floors',
                    'add' => true,
                    'delete' => true,
                    'sort' => [
                        'column' => 'level',
                        'direction' => 'descending'
                    ],
                    'columns' => [
                        [
                            'caption' => 'ID',
                            'name' => 'id',
                            'width' => '75px',
                            'add' => '',
                            'save' => true
                        ],
                        [
                            'caption' => 'Level',
                            'name' => 'level',
                            'width' => '100px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'NumberSpinner'
                            ]
                        ],
                        [
                            'caption' => 'Name',
                            'name' => 'name',
                            'width' => 'auto',
                            'add' => '',
                            'edit' => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'caption' => 'Type',
                            'name' => 'type',
                            'width' => '200px',
                            'add' => 'GroundFloor',
                            'edit' => [
                                'type' => 'Select',
                                'options' => [
                                    [
                                        'caption' => 'Basement',
                                        'value' => 'Basement'
                                    ],
                                    [
                                        'caption' => 'Ground Floor',
                                        'value' => 'GroundFloor'
                                    ],
                                    [
                                        'caption' => 'Upper Floor',
                                        'value' => 'UpperFloor'
                                    ],
                                    [
                                        'caption' => 'Attic',
                                        'value' => 'Attic'
                                    ],
                                    [
                                        'caption' => 'Misc',
                                        'value' => 'Misc'
                                    ]
                                ]
                            ]
                        ],
                        [
                            'caption' => 'Map',
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
                    'rowCount' => 10,
                    'caption' => 'Rooms',
                    'add' => true,
                    'delete' => true,
                    'sort' => [
                        'column' => 'floor',
                        'direction' => 'descending'
                    ],
                    'columns' => [
                        [
                            'caption' => 'ID',
                            'name' => 'id',
                            'width' => '75px',
                            'add' => '',
                            'save' => true
                        ],
                        [
                            'caption' => 'Floor',
                            'name' => 'floor',
                            'width' => '200px',
                            'add' => $floorAdd,
                            'edit' => [
                                'type' => 'Select',
                                'options' => $floorOptions
                            ]
                        ],
                        [
                            'caption' => 'Name',
                            'name' => 'name',
                            'width' => 'auto',
                            'add' => '',
                            'edit' => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'caption' => 'Type',
                            'name' => 'type',
                            'width' => '200px',
                            'add' => 'LivingRoom',
                            'edit' => [
                                'type' => 'Select',
                                'options' => [
                                    [
                                        'caption' => 'Kitchen',
                                        'value' => 'Kitchen'
                                    ],
                                    [
                                        'caption' => 'Living Room',
                                        'value' => 'LivingRoom'
                                    ],
                                    [
                                        'caption' => 'Bed Room',
                                        'value' => 'BedRoom'
                                    ],
                                    [
                                        'caption' => 'Childs Room',
                                        'value' => 'ChildsRoom'
                                    ],
                                    [
                                        'caption' => 'Bath Room',
                                        'value' => 'BathRoom'
                                    ],
                                    [
                                        'caption' => 'Store Room',
                                        'value' => 'StoreRoom'
                                    ],
                                    [
                                        'caption' => 'Play Room',
                                        'value' => 'PlayRoom'
                                    ],
                                    [
                                        'caption' => 'Work Room',
                                        'value' => 'WorkRoom'
                                    ],
                                    [
                                        'caption' => 'Basement Room',
                                        'value' => 'BasementRoom'
                                    ],
                                    [
                                        'caption' => 'Garage',
                                        'value' => 'Garage'
                                    ],
                                    [
                                        'caption' => 'Misc',
                                        'value' => 'Misc'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'values' => []
                ],
                [
                    'type' => 'List',
                    'name' => 'RoomConnections',
                    'rowCount' => 10,
                    'caption' => 'Connections between rooms',
                    'add' => true,
                    'delete' => true,
                    'columns' => [
                        [
                            'caption' => 'First Room',
                            'name' => 'room1',
                            'width' => '200px',
                            'add' => $roomAdd,
                            'edit' => [
                                'type' => 'Select',
                                'options' => $roomOptions
                            ]
                        ],
                        [
                            'caption' => 'Second Room',
                            'name' => 'room2',
                            'width' => '200px',
                            'add' => $roomAdd,
                            'edit' => [
                                'type' => 'Select',
                                'options' => $roomOptions
                            ]
                        ]
                    ],
                    'values' => []
                ],
                [
                    'type' => 'List',
                    'name' => 'Persons',
                    'rowCount' => 5,
                    'caption' => 'Persons',
                    'add' => true,
                    'delete' => true,
                    'columns' => [
                        [
                            'caption' => 'ID',
                            'name' => 'id',
                            'width' => '75px',
                            'add' => '',
                            'save' => true
                        ],
                        [
                            'caption' => 'Name',
                            'name' => 'name',
                            'width' => 'auto',
                            'add' => '',
                            'edit' => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'caption' => 'Birthday',
                            'name' => 'birthday',
                            'width' => '200px',
                            'add' => '{ "year": 0, "month": 0, "day": 0}',
                            'edit' => [
                                'type' => 'SelectDate'
                            ]
                        ],
                        [
                            'caption' => 'Diseases',
                            'name' => 'diseases',
                            'width' => '250px',
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
                    'rowCount' => 10,
                    'caption' => 'Smoke Detectors',
                    'add' => true,
                    'delete' => true,
                    'columns' => [
                        [
                            'caption' => 'ID',
                            'name' => 'id',
                            'width' => '75px',
                            'add' => '',
                            'save' => true
                        ],
                        [
                            'caption' => 'Room',
                            'name' => 'room',
                            'width' => '200px',
                            'add' => $roomAdd,
                            'edit' => [
                                'type' => 'Select',
                                'options' => $roomOptions
                            ]
                        ],
                        [
                            'caption' => 'Variable',
                            'name' => 'variableID',
                            'width' => 'auto',
                            'add' => 0,
                            'edit' => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'caption' => 'Map Position X',
                            'name' => 'x',
                            'width' => '130px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'NumberSpinner'
                            ]
                        ],
                        [
                            'caption' => 'Map Position Y',
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
                    'rowCount' => 10,
                    'caption' => 'Temperature Sensors',
                    'add' => true,
                    'delete' => true,
                    'columns' => [
                        [
                            'caption' => 'ID',
                            'name' => 'id',
                            'width' => '75px',
                            'add' => '',
                            'save' => true
                        ],
                        [
                            'caption' => 'Room',
                            'name' => 'room',
                            'width' => '200px',
                            'add' => $roomAdd,
                            'edit' => [
                                'type' => 'Select',
                                'options' => $roomOptions
                            ]
                        ],
                        [
                            'caption' => 'Variable',
                            'name' => 'variableID',
                            'width' => 'auto',
                            'add' => 0,
                            'edit' => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'caption' => 'Map Position X',
                            'name' => 'x',
                            'width' => '130px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'NumberSpinner'
                            ]
                        ],
                        [
                            'caption' => 'Map Position Y',
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
                    'rowCount' => 10,
                    'caption' => 'Motion Sensors',
                    'add' => true,
                    'delete' => true,
                    'columns' => [
                        [
                            'caption' => 'ID',
                            'name' => 'id',
                            'width' => '75px',
                            'add' => '',
                            'save' => true
                        ],
                        [
                            'caption' => 'Room',
                            'name' => 'room',
                            'width' => '200px',
                            'add' => $roomAdd,
                            'edit' => [
                                'type' => 'Select',
                                'options' => $roomOptions
                            ]
                        ],
                        [
                            'caption' => 'Variable',
                            'name' => 'variableID',
                            'width' => 'auto',
                            'add' => 0,
                            'edit' => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'caption' => 'Map Position X',
                            'name' => 'x',
                            'width' => '130px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'NumberSpinner'
                            ]
                        ],
                        [
                            'caption' => 'Map Position Y',
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
                    'rowCount' => 10,
                    'caption' => 'Doors',
                    'add' => true,
                    'delete' => true,
                    'columns' => [
                        [
                            'caption' => 'ID',
                            'name' => 'id',
                            'width' => '75px',
                            'add' => '',
                            'save' => true
                        ],
                        [
                            'caption' => 'Room',
                            'name' => 'room',
                            'width' => '200px',
                            'add' => $roomAdd,
                            'edit' => [
                                'type' => 'Select',
                                'options' => $roomOptions
                            ]
                        ],
                        [
                            'caption' => 'Variable',
                            'name' => 'variableID',
                            'width' => 'auto',
                            'add' => 0,
                            'edit' => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'caption' => 'Map Position X',
                            'name' => 'x',
                            'width' => '130px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'NumberSpinner'
                            ]
                        ],
                        [
                            'caption' => 'Map Position Y',
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
                    'name' => 'Actors',
                    'rowCount' => 10,
                    'caption' => 'Actors',
                    'add' => true,
                    'delete' => true,
                    'columns' => [
                        [
                            'caption' => 'ID',
                            'name' => 'id',
                            'width' => '75px',
                            'add' => '',
                            'save' => true
                        ],
                        [
                            'caption' => 'Room',
                            'name' => 'room',
                            'width' => '200px',
                            'add' => $roomAdd,
                            'edit' => [
                                'type' => 'Select',
                                'options' => $roomOptions
                            ]
                        ],
                        [
                            'caption' => 'Variable',
                            'name' => 'variableID',
                            'width' => 'auto',
                            'add' => 0,
                            'edit' => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'caption' => 'Map Position X',
                            'name' => 'x',
                            'width' => '130px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'NumberSpinner'
                            ]
                        ],
                        [
                            'caption' => 'Map Position Y',
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
                    'type' => 'Select',
                    'name' => 'PersonDetectionMode',
                    'caption' => 'Person Detection Mode',
                    'options' => [
                        [
                            'value' => 0,
                            'caption' => 'Regular'
                        ],
                        [
                            'value' => 1,
                            'caption' => 'Simplified Single Person Detection'
                        ]
                    ]
                ]
            ]
        ]);
    }

    public function MessageSink($timestamp, $senderID, $message, $data) {
        switch ($message) {
            case 10603: // VM_UPDATE
                // Variable is a Motion Sensor?
                $triggeredRoom = 0;
                $isMotionSensor = false;
                foreach (json_decode($this->ReadPropertyString('MotionSensors'), true) as $motionSensor) {
                    if ($motionSensor['variableID'] == $senderID) {
                        $isMotionSensor = true;
                        $triggeredRoom = $motionSensor['room'];
                        break;
                    }
                }
                // Variable is a Actor?
                $isActor = false;
                if (!$isMotionSensor) {
                    foreach (json_decode($this->ReadPropertyString('Actors'), true) as $actor) {
                        if ($actor['variableID'] == $senderID) {
                            $isActor = true;
                            $triggeredRoom = $actor['room'];
                            break;
                        }
                    }
                }
                // Only update on positive motion sensor notifications, but on every actor action
                if (($isMotionSensor && $data[0]) || $isActor) {
                    $neighboringRooms = [];
                    foreach (json_decode($this->ReadPropertyString('RoomConnections'), true) as $roomConnection) {
                        if (($roomConnection['room1'] == $triggeredRoom) && (!in_array($roomConnection['room2'], $neighboringRooms))) {
                            $neighboringRooms[] = $roomConnection['room2'];
                        }

                        if (($roomConnection['room2'] == $triggeredRoom) && (!in_array($roomConnection['room1'], $neighboringRooms))) {
                            $neighboringRooms[] = $roomConnection['room1'];
                        }
                    }
                    $this->SendDebug('Motion Sensor - Neighboring Rooms', json_encode($neighboringRooms), 0);

                    $reportBlocked = false;
                    if ($isMotionSensor) {
                        $this->SendDebug('Sensor blocked? - Smoke Detectors', $this->ReadPropertyString('SmokeDetectors'), 0);
                        $reportBlocked = false;
                        switch ($this->ReadPropertyInteger('PersonDetectionMode')) {
                            case 0: // Regular mode
                                $smokeDetectedOwnRoom = false;
                                $roomHasSmokeDetectors = false;
                                $smokeDetectorRoomAbove = false;
                                foreach (json_decode($this->ReadPropertyString('SmokeDetectors'), true) as $smokeDetector) {
                                    if ($smokeDetector['room'] == $triggeredRoom) {
                                        $this->SendDebug('Sensor blocked?', 'Smoke Detector in room', 0);
                                        $roomHasSmokeDetectors = true;
                                        if (GetValue($smokeDetector['variableID'])) {
                                            $this->SendDebug('Sensor blocked?', 'Smoke Detector in room triggered!', 0);
                                            $smokeDetectedOwnRoom = true;
                                            break;
                                        }
                                    } elseif
                                        (in_array($smokeDetector['room'], $neighboringRooms) &&
                                            ($this->GetRoomLevelByIRISID($triggeredRoom) < $this->GetRoomLevelByIRISID($smokeDetector['room'])) &&
                                            GetValue($smokeDetector['variableID'])){
                                        $this->SendDebug('Sensor blocked?', 'Smoke Detector in room above triggered', 0);
                                        $smokeDetectorRoomAbove = true;
                                    }
                                }
                                $reportBlocked = $smokeDetectedOwnRoom || (!$roomHasSmokeDetectors && $smokeDetectorRoomAbove);
                                break;

                            case 1: // Simplified mode
                                foreach (json_decode($this->ReadPropertyString('SmokeDetectors'), true) as $smokeDetector) {
                                    if (GetValue($smokeDetector['variableID'])) {
                                        $this->SendDebug('Sensor blocked? - simplified', 'Smoke Detector triggered!', 0);
                                        $reportBlocked = true;
                                        break;
                                    }
                                }
                                break;
                        }
                    }

                    if (!$reportBlocked) {

                        switch ($this->ReadPropertyInteger('PersonDetectionMode')) {
                            case 0: // Regular mode
                                $this->SendDebug('Motion Sensor', 'Activated', 0);
                                $freePersonID = 2000; // FIXME: For the time being, just start at 2000 to avoid overlap with other IDs
                                $personsInSameRoom = [];
                                $personsInNeighboringRooms = [];
                                $detectedPersons = json_decode(GetValue($this->GetIDForIdent('DetectedPersons')), true);

                                foreach ($detectedPersons as $personIDString => $personData) {
                                    $personID = intval($personIDString);
                                    if ($freePersonID <= $personID) {
                                        $freePersonID = $personID + 1;
                                    }

                                    foreach ($personData as $likelyPosition) {
                                        if (($likelyPosition['room'] == $triggeredRoom) && !in_array($personID, $personsInSameRoom)) {
                                            $this->SendDebug('Motion Sensor', 'Detected in same room', 0);
                                            $personsInSameRoom[] = $personID;
                                        }

                                        if (in_array($likelyPosition['room'], $neighboringRooms) && !in_array($personID, $personsInSameRoom)) {
                                            $this->SendDebug('Motion Sensor', 'Detected in neighboring room', 0);
                                            $personsInNeighboringRooms[] = $personID;
                                        }
                                    }
                                }

                                // There are persons in the same room as the motion sensor => Refresh probability to initial probability
                                if (sizeof($personsInSameRoom) > 0) {
                                    $this->SendDebug('Motion Sensor', 'Persons in same room', 0);
                                    foreach ($personsInSameRoom as $personID) {
                                        foreach ($detectedPersons[strval($personID)] as &$likelyPosition) {
                                            if ($likelyPosition['room'] == $triggeredRoom) {
                                                $this->AddPreviousLocation($likelyPosition);
                                                $likelyPosition['probability'] = self::INITIAL_PROBABILITY_MOTION;
                                                $likelyPosition['lastConfirmation'] = $data[3];
                                                break;
                                            }
                                        }
                                    }
                                } elseif (sizeof($personsInNeighboringRooms) > 0) {
                                    $this->SendDebug('Motion Sensor', 'Persons in neighboring rooms', 0);
                                    $movePersonID = $personsInNeighboringRooms[0];
                                    $currentProbability = 0;
                                    $currentConfirmation = 0;
                                    $currentNeighboringRoom = 0;
                                    foreach ($personsInNeighboringRooms as $personID) {
                                        foreach ($detectedPersons[strval($personID)] as &$likelyPosition) {
                                            if (in_array($likelyPosition['room'], $neighboringRooms) &&
                                                (($likelyPosition['probability'] > $currentProbability) ||
                                                    (($likelyPosition['probability'] == $currentProbability) &&
                                                        ($likelyPosition['lastConfirmation'] > $currentConfirmation)))) {
                                                $movePersonID = $personID;
                                                $currentProbability = $likelyPosition['probability'];
                                                $currentConfirmation = $likelyPosition['lastConfirmation'];
                                                $currentNeighboringRoom = $likelyPosition['room'];
                                            }
                                        }
                                    }

                                    foreach ($detectedPersons[strval($movePersonID)] as &$likelyPosition) {
                                        if ($likelyPosition['room'] == $currentNeighboringRoom) {
                                            // Check if a person moved back and forth for a fourth time, i.e., neighboring room -> this room -> neighboring room -> this room
                                            // In that case, we assume that it's two persons and not a single one running back and forth
                                            $createNewPerson = false;
                                            if (sizeof($likelyPosition['previousLocations']) >= 3) {
                                                $switchTimes = 0;
                                                $currentlyCheckedRoom = $likelyPosition['room'];
                                                $otherRoom = $triggeredRoom;
                                                foreach ($likelyPosition['previousLocations'] as $previousLocation) {
                                                    if (($currentlyCheckedRoom != $previousLocation['room']) && ($otherRoom != $previousLocation['room'])) {
                                                        break;
                                                    }

                                                    if ($previousLocation['room'] == $otherRoom) {
                                                        $switchTimes++;
                                                        $otherRoom = $currentlyCheckedRoom;
                                                        $currentlyCheckedRoom = $previousLocation['room'];
                                                    }
                                                }
                                                $createNewPerson = ($switchTimes >= 3);
                                            }
                                            if ($createNewPerson) {
                                                $this->SendDebug('Motion Sensor', 'Too much moving back and forth, so create a new one', 0);
                                                $detectedPersons[strval($freePersonID)] = [[
                                                    'previousLocations' => [],
                                                    'room' => $triggeredRoom,
                                                    'probability' => self::INITIAL_PROBABILITY_MOTION,
                                                    'lastConfirmation' => $data[3]
                                                ]];
                                            }
                                            else {
                                                $this->AddPreviousLocation($likelyPosition);
                                                $likelyPosition['room'] = $triggeredRoom;
                                                $likelyPosition['probability'] = self::INITIAL_PROBABILITY_MOTION;
                                                $likelyPosition['lastConfirmation'] = $data[3];
                                            }
                                            $this->SendDebug('Motion Sensor - New detected persons', json_encode($detectedPersons), 0);
                                            break;
                                        }

                                    }
                                } // New person required as no existing is near triggered motion sensor
                                else {
                                    $this->SendDebug('Motion Sensor', 'No person nearby, so create a new one', 0);
                                    $detectedPersons[strval($freePersonID)] = [[
                                        'previousLocations' => [],
                                        'room' => $triggeredRoom,
                                        'probability' => self::INITIAL_PROBABILITY_MOTION,
                                        'lastConfirmation' => $data[3]
                                    ]];
                                }
                                SetValue($this->GetIDForIdent('DetectedPersons'), json_encode($detectedPersons));
                                break;

                            case 1: // Simplified Single Person Mode
                                $detectedPersons = json_decode(GetValue($this->GetIDForIdent('DetectedPersons')), true);
                                // There is a person already, so move it to the other room
                                if (isset($detectedPersons['2000'])) {
                                    foreach ($detectedPersons['2000'] as &$likelyPosition) {
                                        $this->AddPreviousLocation($likelyPosition);
                                        $likelyPosition['room'] = $triggeredRoom;
                                        $likelyPosition['probability'] = self::INITIAL_PROBABILITY_MOTION;
                                        $likelyPosition['lastConfirmation'] = $data[3];

                                    }
                                }
                                else {
                                    $detectedPersons = [
                                        '2000' => [[
                                            'previousLocations' => [],
                                            'room' => $triggeredRoom,
                                            'probability' => self::INITIAL_PROBABILITY_MOTION,
                                            'lastConfirmation' => $data[3]
                                        ]]
                                    ];
                                }
                                SetValue($this->GetIDForIdent('DetectedPersons'), json_encode($detectedPersons));
                                break;

                        }
                    }
                }

                // Variable was a Smoke Detectors?
                // Only check for newly triggered smoke detectors
                if ($data[0] && $data[1]) {
                    foreach (json_decode($this->ReadPropertyString('SmokeDetectors'), true) as $smokeDetector) {
                        if ($smokeDetector['variableID'] == $senderID) {
                            // Determine affected rooms = room with smoke detector and lower rooms without smoke detectors
                            $affectedRooms = [$smokeDetector['room']];
                            foreach (json_decode($this->ReadPropertyString('RoomConnections'), true) as $roomConnection) {
                                $potentialLowerRoom = -1;
                                if (($roomConnection['room1'] == $smokeDetector['room']) && (!in_array($roomConnection['room2'], $neighboringRooms))) {
                                    $potentialLowerRoom = $roomConnection['room2'];
                                }

                                if (($roomConnection['room2'] == $smokeDetector['room']) && (!in_array($roomConnection['room1'], $neighboringRooms))) {
                                    $potentialLowerRoom = $roomConnection['room1'];
                                }

                                if (($potentialLowerRoom != -1) && ($this->GetRoomLevelByIRISID($potentialLowerRoom) < $this->GetRoomLevelByIRISID($smokeDetector['room']))) {
                                    $roomHasOwnSmokeDetector = false;
                                    foreach (json_decode($this->ReadPropertyString('SmokeDetectors'), true) as $lowerSmokeDetector) {
                                        if ($lowerSmokeDetector['room'] == $potentialLowerRoom) {
                                            $roomHasOwnSmokeDetector = true;
                                            break;
                                        }
                                    }
                                    if (!$roomHasOwnSmokeDetector) {
                                        $affectedRooms[] = $potentialLowerRoom;
                                    }
                                }
                            }

                            $update = false;
                            $detectedPersons = json_decode(GetValue($this->GetIDForIdent('DetectedPersons')), true);
                            foreach ($detectedPersons as $personIDString => &$personData) {
                                foreach ($personData as $i => &$likelyPosition) {
                                    if (in_array($likelyPosition['room'], $affectedRooms) && ($likelyPosition['lastConfirmation'] >= ($data[3] - self::SMOKE_DETECTOR_TURNBACK))) {
                                        $this->SendDebug('Undo movement from smoke detector', 'Start', 0);
                                        $update = true;
                                        $usedIndex = 0;
                                        while (($usedIndex < sizeof($likelyPosition['previousLocations'])) &&
                                            in_array($likelyPosition['previousLocations'][$usedIndex], $affectedRooms) &&
                                            ($likelyPosition['previousConfirmations'][$usedIndex] >= ($data[3] - self::SMOKE_DETECTOR_TURNBACK))) {
                                            $usedIndex++;
                                            $this->SendDebug('Undo movement from smoke detector, new usedIndex = ', $usedIndex, 0);
                                        }

                                        $this->SendDebug('Undo movement from smoke detector - previousLocations', json_encode($likelyPosition['previousLocations']), 0);
                                        $this->SendDebug('Undo movement from smoke detector', 'Remove person', 0);
                                        if ($usedIndex == sizeof($likelyPosition['previousLocations'])) {
                                            $this->SendDebug('Undo movement from smoke detector', 'Remove person', 0);

                                            unset($personData[$i]);
                                            if (sizeof($personData) == 0) {
                                                unset($detectedPersons[$personIDString]);
                                            }
                                        } else {
                                            $this->SendDebug('Undo movement from smoke detector', 'Rollback person', 0);
                                            $likelyPosition['room'] = $likelyPosition['previousLocations'][$usedIndex];
                                            $likelyPosition['previousLocations'] = array_slice($likelyPosition['previousLocations'], $usedIndex + 1);
                                            $this->SendDebug('Undo movement from smoke detector - New likely position', json_encode($likelyPosition), 0);
                                        }
                                    }
                                }
                            }

                            if ($update) {
                                $this->SendDebug('Write new value', json_encode($detectedPersons), 0);
                                SetValue($this->GetIDForIdent('DetectedPersons'), json_encode($detectedPersons));
                            }
                        }
                    }
                }

                $this->ApplyProbabilityDecay($data[3]);
                break;
        }
    }

    public function ChangePositionOfPerson($personID, $newRoomPosition) {
        $persons = json_decode($this->ReadPropertyString('Persons'), true);

        for ($i = 0; $i < sizeof($persons); $i++) {
            if (intval($persons[$i]['id']) == $personID) {
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
                    'rooms' => $this->GetObjectListRooms(),
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
                $this->ReturnResult($request['id'], $this->SwitchVariable(intval($request['params']['id']), $request['params']['value']));
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

            $person['id'] = intval($person['id']);

            if ($person['name'] == '') {
                unset($person['name']);
            }

            $birthdayData = json_decode($person['birthday'], true);
            $birthday = mktime(0,0,0,$birthdayData['month'], $birthdayData['day'], $birthdayData['year']);
            if ($birthday == 0) {
                unset($person['birthday']);
            }
            else {
                $person['birthday'] = $birthday;
            }

            if ($person['diseases'] == '') {
                unset($person['diseases']);
            }
            else {
                $person['diseases'] = explode(',', $person['diseases']);
            }

            $person['coreData'] = true;

            $result[] = $person;
        }

        foreach (json_decode(GetValue($this->GetIDForIdent('DetectedPersons')), true) as $personIDString => $personData) {
            $result[] = [
                'id' => intval($personIDString),
                'coreData' => false
            ];
        }

        return $result;

    }

    private function GetObjectListFloors() {
        $result = [];

        foreach (json_decode($this->ReadPropertyString('Floors'), true) as $floor) {
            unset($floor['map']);
            $floor['id'] = intval($floor['id']);
            $result[] = $floor;
        }

        return $result;
    }

    private function GetObjectListRooms() {
        $result = [];

        foreach (json_decode($this->ReadPropertyString('Rooms'), true) as $room) {
            $room['id'] = intval($room['id']);
            $result[] = $room;
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
            'id' => intval($value['id']),
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
                'floor' => intval($floor['id']),
                'map' => $floor['map']
            ];
        }

        return $maps;
    }

    private function ComputeStatus($ids) {
        $this->ApplyProbabilityDecay(time());

        $persons = [];
        foreach (json_decode($this->ReadPropertyString('Persons'), true) as $person) {
            if ((sizeof($ids) == 0) || in_array(intval($person['id']), $ids)) {
                $persons[] = [
                    'id' => intval($person['id']),
                    'present' => 'Unknown'
                ];
            }
        }

        foreach(json_decode(GetValue($this->GetIDForIdent('DetectedPersons')), true) as $personIDString => $personData) {
            $personID = intval($personIDString);
            if ((sizeof($ids) == 0) || in_array(intval($personIDString), $ids)) {

                $likelyPositions = [];
                foreach ($personData as $likelyPosition) {
                    $likelyPositions[] = [
                        'room' => $likelyPosition['room'],
                        'probability' => $likelyPosition['probability']
                    ];
                }

                $persons[] = [
                    'id' => $personID,
                    'present' => 'Present',
                    'likelyPositions' => $likelyPositions
                ];
            }
        }

        $rooms = [];
        foreach (json_decode($this->ReadPropertyString('Rooms'), true) as $room) {
            if ((sizeof($ids) == 0) || in_array(intval($room['id']), $ids)) {
                $rooms[] = [
                    'id' => intval($room['id']),
                    'status' => $this->ComputeStatusOfRoom($room['id'])
                ];
            }
        }

        $devices = [];
        foreach (json_decode($this->ReadPropertyString('SmokeDetectors'), true) as $smokeDetector) {
            if ((sizeof($ids) == 0) || in_array(intval($smokeDetector['id']), $ids)) {
                $devices[] = [
                    'id' => intval($smokeDetector['id']),
                    'lastUpdate' => IPS_GetVariable($smokeDetector['variableID'])['VariableUpdated'],
                    'lastChange' => IPS_GetVariable($smokeDetector['variableID'])['VariableChanged'],
                    'value' => [
                        'smoke' => GetValue($smokeDetector['variableID']) ? 1.0 : 0.0
                    ]
                ];
            }
        }

        foreach (json_decode($this->ReadPropertyString('TemperatureSensors'), true) as $temperatureSensor) {
            if (((sizeof($ids) == 0) || in_array(intval($temperatureSensor['id']), $ids)) && IPS_VariableExists($temperatureSensor['variableID'])) {
                $devices[] = [
                    'id' => intval($temperatureSensor['id']),
                    'lastUpdate' => IPS_GetVariable($temperatureSensor['variableID'])['VariableUpdated'],
                    'lastChange' => IPS_GetVariable($temperatureSensor['variableID'])['VariableChanged'],
                    'value' => [
                        'temperature' => GetValue($temperatureSensor['variableID'])
                    ]
                ];
            }
        }

        foreach (json_decode($this->ReadPropertyString('Doors'), true) as $door) {
            if ((sizeof($ids) == 0) || in_array(intval($door['id']), $ids)) {
                $devices[] = [
                    'id' => intval($door['id']),
                    'lastUpdate' => IPS_GetVariable($door['variableID'])['VariableUpdated'],
                    'lastChange' => IPS_GetVariable($door['variableID'])['VariableChanged'],
                    'value' => [
                        'open' => GetValueBoolean($door['variableID'])
                    ]
                ];
            }
        }

        foreach (json_decode($this->ReadPropertyString('MotionSensors'), true) as $motionSensor) {
            if ((sizeof($ids) == 0) || in_array(intval($motionSensor['id']), $ids)) {
                $devices[] = [
                    'id' => intval($motionSensor['id']),
                    'lastUpdate' => IPS_GetVariable($motionSensor['variableID'])['VariableUpdated'],
                    'lastChange' => IPS_GetVariable($motionSensor['variableID'])['VariableChanged'],
                    'value' => [
                        'motionDetected' => GetValueBoolean($motionSensor['variableID'])
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

        foreach (json_decode($this->ReadPropertyString('SmokeDetectors'), true) as $smokeDetector) {
            if (($smokeDetector['room'] == $roomID) && IPS_VariableExists($smokeDetector['variableID']) && GetValue($smokeDetector['variableID'])) {
                $status[] = 'Smoked';
                break;
            }
        }

        foreach (json_decode($this->ReadPropertyString('TemperatureSensors'), true) as $temperatureSensor) {
            if (($temperatureSensor['room'] == $roomID) && IPS_VariableExists($temperatureSensor['variableID']) && (GetValue($temperatureSensor['variableID']) > 100)) {
                $status[] = 'Burning';
                break;
            }
        }

        return $status;
    }

    private function ComputeAlarm() {
        $alarmTypes = json_decode($this->ReadPropertyString('AlarmTypes'));
        if (!in_array('Fire', $alarmTypes)) {
            foreach (json_decode($this->ReadPropertyString('Rooms'), true) as $room) {
                $roomStatus = $this->ComputeStatusOfRoom(intval($room['id']));
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

    private function GetRoomLevelByIRISID($roomID) {
        $floorID = -1;
        foreach (json_decode($this->ReadPropertyString("Rooms"), true) as $value) {
            if (intval($value['id']) == $roomID) {
                $floorID = $value['floor'];
            }
        }
        if ($floorID == -1) {
            throw new Exception('Room not found');
        }


        foreach (json_decode($this->ReadPropertyString("Floors"), true) as $value) {
            if (intval($value['id']) == $floorID) {
                return $value['level'];
            }
        }

        throw new Exception('Floor not found');
    }

    private function GetVariableIDByIRISID($irisID) {
        foreach (["SmokeDetectors", "MotionSensors", "Doors"] as $property) {
            foreach (json_decode($this->ReadPropertyString($property), true) as $value) {
                if (intval($value['id']) == $irisID) {
                    return $value['variableID'];
                }
            }
        }

        $this->SendDebug("IRiS - Error", "Variable for IRiS ID does not exist", 0);
        return 0;
    }

    private function FillIDs() {
        $availableID = 0;
        foreach(["Floors", "Rooms", "Persons", "SmokeDetectors", "TemperatureSensors", "MotionSensors", "Doors", "Actors"] as $property) {
            foreach (json_decode($this->ReadPropertyString($property), true) as $value) {
                if ($value['id'] != '') {
                    $availableID = max($availableID, intval($value['id']));
                }
            }
        }
        $availableID++; // AvailableID is now one more than the highest ID

        $changed = false;
        foreach(["Floors", "Rooms", "Persons", "SmokeDetectors", "TemperatureSensors", "MotionSensors", "Doors", "Actors"] as $property) {
            $update = false;
            $data = json_decode($this->ReadPropertyString($property), true);
            foreach ($data as &$value) {
                if ($value['id'] == '') {
                    $value['id'] = strval($availableID);
                    $availableID++;
                    $changed = true;
                    $update = true;
                }
            }

            if ($update) {
                IPS_SetProperty($this->InstanceID, $property, json_encode($data));
                $this->SendDebug('Setting property', $property . ' = ' . json_encode($data), 0);
            }
        }

        if ($changed) {
            $this->SendDebug('Fill IDs', 'Changed -> Apply Changes again', 0);
            IPS_ApplyChanges($this->InstanceID);
        }
    }

    private function ApplyProbabilityDecay($timestamp) {
        $this->SendDebug('Propability Decay - Timestamp', $timestamp, 0);
        $detectedPersons = json_decode(GetValue($this->GetIDForIdent('DetectedPersons')), true);

        foreach ($detectedPersons as $personIDString => &$personData) {
            $this->SendDebug('Propability Decay - Person Data', json_encode($personData), 0);
            foreach($personData as $i => &$likelyPosition) {

                $updateProbability = true;
                foreach (json_decode($this->ReadPropertyString('MotionSensors'), true) as $motionSensor) {
                    if (($motionSensor['room'] == $likelyPosition['room']) && (GetValue($motionSensor['variableID']))) {
                        $updateProbability = false;
                    }
                }

                if ($updateProbability) {
                    $likelyPosition['probability'] = self::INITIAL_PROBABILITY_MOTION - (($timestamp - $likelyPosition['lastConfirmation']) / self::DECAY_PROBABILITY_MOTION) * 0.01;
                    if ($likelyPosition['probability'] <= 0) {
                        unset($personData[$i]);
                        if (sizeof($personData) == 0) {
                            unset($detectedPersons[$personIDString]);
                        }
                    }
                }
            }
        }

        SetValue($this->GetIDForIdent('DetectedPersons'), json_encode($detectedPersons));
    }

    private function AddPreviousLocation(&$likelyPosition) {
        array_unshift($likelyPosition['previousLocations'], [
            'room' => $likelyPosition['room'],
            'confirmation' => $likelyPosition['lastConfirmation']
        ]);
        $likelyPosition['previousLocations'] = array_slice($likelyPosition['previousLocations'], 0, self::STORED_PREVIOUS_DATA);
    }
}

?>