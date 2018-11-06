<?

include __DIR__ . "/../libs/WebHookModule.php";
include_once __DIR__ . '/helper/autoload.php';

class IRiS extends WebHookModule {

    use HelperDimDevice;
    use HelperSwitchDevice;

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
        $this->RegisterPropertyInteger("PresenceGeneral", 0);

        $this->RegisterPropertyString("Floors", "[]");
        $this->RegisterPropertyString("Rooms", "[]");
        $this->RegisterPropertyString("Persons", "[]");
        $this->RegisterPropertyString("SmokeDetectors", "[]");
        $this->RegisterPropertyString("TemperatureSensors", "[]");
        $this->RegisterPropertyString("Doors", "[]");
        $this->RegisterPropertyString("Lights", "[]");
        $this->RegisterPropertyString("EmergencyOff", "[]");
        $this->RegisterPropertyString("Shutters", "[]");

        $this->RegisterAttributeString("AlarmTypes", "[]");
    }

    public function Destroy(){
        //Never delete this line!
        parent::Destroy();
        
    }

    public function ApplyChanges(){
        //Never delete this line!
        parent::ApplyChanges();

        $this->FillIDs();
    }
    
    public function GetConfigurationForm() {
        $cmpFloors = function($first, $second) {
            return ($first['level'] > $second['level']) ? -1 : 1;
        };
        $floors = json_decode($this->ReadPropertyString('Floors'), true);
        usort($floors, $cmpFloors);
        $floorOptions = [];
        $floorMaps = [];
        foreach ($floors as $floor) {
            $floorOptions[] = [
                'caption' => $floor['name'],
                'value' => intval($floor['id'])
            ];

            $map = base64_decode($floor['map']);
            $bracketPosition = strpos($map, '>'); // Find closing bracket of initial svg-tag
            if ($bracketPosition !== false) {
                $map = substr($map, 0, $bracketPosition) .
                    ' width="800"><style>
                    .room {
                      fill: red;
                      fill-opacity: 0.3;
                    }
                    
                    .wall {
                      stroke: black;
                      fill: none;
                    }
                    
                    .door {
                      stroke: blue;
                      stroke-width: 3;
                    }
                    
                    .window {
                      stroke: green;
                      stroke-width: 5;
                    }
                    
                    .window-2 {
                      stroke: purple;
                      stroke-width: 5;
                    }
                    
                    .stairs {
                      stroke: grey;
                      fill: white
                    }
                    </style>' .
                    substr($map, $bracketPosition + 1);

                $floorMaps[] = [
                    'type' => 'ExpansionPanel',
                    'caption' => $floor['name'],
                    'items' => [
                        [
                            'type' => 'Image',
                            'image' => 'data:image/svg+xml;base64,' . base64_encode($map)
                        ]
                    ]
                ];

            }



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
            'elements' => array_merge([
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
                    'type' => 'SelectVariable',
                    'name' => 'PresenceGeneral',
                    'caption' => 'General Presence'
                ]
            ],
            $floorMaps,
            [
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
                        ],
                        [
                            'caption' => 'Map: Pixels per Meter',
                            'name' => 'pixelsPerMeter',
                            'width' => '180px',
                            'add' => 1,
                            'edit' => [
                                'type' => 'NumberSpinner',
                                'digits' => 2
                            ]
                        ],
                        [
                            'caption' => 'Map: North Degree',
                            'name' => 'north',
                            'width' => '150px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'NumberSpinner',
                                'digits' => 2,
                                'suffix' => '°'
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
                        ],
                        [
                            'caption' => 'Presence',
                            'name' => 'presence',
                            'width' => '100px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'SelectVariable'
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
                    'name' => 'Lights',
                    'rowCount' => 10,
                    'caption' => 'Lights',
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
                    'name' => 'EmergencyOff',
                    'rowCount' => 10,
                    'caption' => 'Emergency Off',
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
                    'name' => 'Shutters',
                    'rowCount' => 10,
                    'caption' => 'Shutters',
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
                ]
            ]),
            'actions' => [
                [
                    'type' => 'SelectFile',
                    'name' => 'IFCFile',
                    'caption' => 'IFC File'
                ],
                [
                    'type' => 'Button',
                    'caption' => 'Load IFC',
                    'onClick' => 'IRIS_LoadIFC($id, $IFCFile);'
                ]
            ]

        ]);
    }

    public function AddAlarm(string $alarmType) {
        $currentAlarmTypes = json_decode($this->ReadAttributeString('AlarmTypes'), true);
        if (!in_array($alarmType, $currentAlarmTypes)) {
            $currentAlarmTypes[] = $alarmType;
            $this->WriteAttributeString('AlarmTypes', json_encode($currentAlarmTypes));
        }
    }

    public function ResetAlarm() {
        $this->WriteAttributeString('AlarmTypes', '[]');
    }

    public function LoadIFC(string $IFCFile) {
        IPS_SetConfiguration($this->InstanceID, json_encode([
            'Address' => 'Steubenstraße 47a, 33100 Paderborn',
            'BuildingMaterial' => 'Stone',
            'HeatingType' => json_encode([
                [
                    'selected' => true
                ],
                [
                    'selected' => false
                ],
                [
                    'selected' => true
                ],
                [
                    'selected' => false
                ],
                [
                    'selected' => false
                ],
                [
                    'selected' => false
                ]
            ]),
            'PresenceGeneral' => 0,
            'Floors' => json_encode([
                [
                    'id' => '1',
                    'level' => 0,
                    'name' => 'Erdgeschoss',
                    'type' => 'GroundFloor',
                    'map' => 'PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB2aWV3Qm94PSIwIDAgMTM0MCA4MjAiPgogIDxyZWN0IGlkPSJtYXBfcm9vbV8zIiBjbGFzcz0icm9vbSIgeD0iNTQ2IiB5PSIzOTQiIHdpZHRoPSIyNjAiIGhlaWdodD0iNDI1Ii8+CiAgPHJlY3QgaWQ9Im1hcF9yb29tXzQiIGNsYXNzPSJyb29tIiB4PSIyNDciIHk9IjQxMyIgd2lkdGg9IjI5OSIgaGVpZ2h0PSIzMzkiLz4KICA8cmVjdCBpZD0ibWFwX3Jvb21fNSIgY2xhc3M9InJvb20iIHg9IjgwNiIgeT0iNTcwIiB3aWR0aD0iMjg5IiBoZWlnaHQ9IjE4MiIvPgogIDxyZWN0IGlkPSJtYXBfcm9vbV84IiBjbGFzcz0icm9vbSIgeD0iODA2IiB5PSIzOTQiIHdpZHRoPSIxNjAiIGhlaWdodD0iOTAiLz4KICA8cGF0aCBpZD0ibWFwX3Jvb21fNiIgY2xhc3M9InJvb20iIGQ9Ik04MDYgNDg0IGgxNjAgdi05MCBoMTI5IHYxNzYgaC0yODkgWiIvPgogIDxwYXRoIGlkPSJtYXBfcm9vbV83IiBjbGFzcz0icm9vbSIgZD0iTSAyNDcgNzQgSDUyNSBWMCBoMjkyIFY3NCBoMjc4IHYzMjAgSDU0NiBWNDEzIEgyNDcgWiIvPgogIAo8cGF0aCBjbGFzcz0id2FsbCIgZD0iTTI0Nyw3NCBINTI1IFYwIGgyOTIgVjc0IGgyNzggdjY3OSBIODA2IHY2NiBINTQ2IHYtNjZIMjQ3IFY3NHoiLz4KICA8cGF0aCBjbGFzcz0id2FsbCIgZD0iTSAyNDcsNDEzIEg1NDYgVjM5NCBIMTA5NSIvPgogIDxwYXRoIGNsYXNzPSJ3YWxsIiBkPSJNIDgwNiwzOTQgdjkwIGggMTYwIHYtOTAiLz4KICA8bGluZSBjbGFzcz0id2FsbCIgeDE9IjgwNiIgeTE9IjQ4NCIgeDI9IjgwNiIgeTI9Ijc1MiIvPgogIDxsaW5lIGNsYXNzPSJ3YWxsIiB4MT0iNTQ2IiB5MT0iNDEzIiB4Mj0iNTQ2IiB5Mj0iNzUyIi8+CiAgPGxpbmUgY2xhc3M9IndhbGwiIHgxPSI4MDYiIHkxPSI1NzAiIHgyPSIxMDk1IiB5Mj0iNTcwIi8+CiAgCiAgPGxpbmUgY2xhc3M9ImRvb3IiIHgxPSI1NDYiIHkxPSI2NzAiIHgyPSI1NDYiIHkyPSI3NDAiLz4KICA8bGluZSBjbGFzcz0iZG9vciIgeDE9IjgwNiIgeTE9IjY3MCIgeDI9IjgwNiIgeTI9Ijc0MCIvPgogIDxsaW5lIGNsYXNzPSJkb29yIiB4MT0iODA2IiB5MT0iNDkyIiB4Mj0iODA2IiB5Mj0iNTYyIi8+CiAgPGxpbmUgY2xhc3M9ImRvb3IiIHgxPSI2MDYiIHkxPSI4MTkiIHgyPSI3MjYiIHkyPSI4MTkiLz4KICA8bGluZSBjbGFzcz0iZG9vciIgeDE9IjcxMiIgeTE9IjM5NCIgeDI9IjgwNiIgeTI9IjM5NCIvPgogIDxsaW5lIGNsYXNzPSJkb29yIiB4MT0iODIwIiB5MT0iMzk0IiB4Mj0iODkwIiB5Mj0iMzk0Ii8+CiAgPGxpbmUgaWQ9Im1hcF9kb29yXzE4IiBjbGFzcz0iZG9vciIgeDE9IjUzMSIgeTE9IjAiIHgyPSI4MTEiIHkyPSIwIi8+CiAgCjxsaW5lIGNsYXNzPSJ3aW5kb3ciIHgxPSIyNDciIHkxPSI0NzciIHgyPSIyNDciIHkyPSI2MDAiLz4KPGxpbmUgY2xhc3M9IndpbmRvdyIgeDE9IjEwOTUiIHkxPSIxOTkiIHgyPSIxMDk1IiB5Mj0iMzE1Ii8+CjxsaW5lIGNsYXNzPSJ3aW5kb3ciIHgxPSIxMDk1IiB5MT0iNTAwIiB4Mj0iMTA5NSIgeTI9IjU1MCIvPgo8bGluZSBjbGFzcz0id2luZG93IiB4MT0iMjQ3IiB5MT0iMjQ0IiB4Mj0iMjQ3IiB5Mj0iMzU1Ii8+CjxsaW5lIGNsYXNzPSJ3aW5kb3ciIHgxPSI0MTAiIHkxPSI3NCIgeDI9IjUxMCIgeTI9Ijc0Ii8+CjxsaW5lIGNsYXNzPSJ3aW5kb3ciIHgxPSI4MzIiIHkxPSI3NCIgeDI9IjkzMiIgeTI9Ijc0Ii8+CjxsaW5lIGNsYXNzPSJ3aW5kb3ciIHgxPSI0MTAiIHkxPSI3NTIiIHgyPSI1MTAiIHkyPSI3NTIiLz4KPGxpbmUgY2xhc3M9IndpbmRvdyIgeDE9IjgzMiIgeTE9Ijc1MiIgeDI9IjkzMiIgeTI9Ijc1MiIvPgogIAo8cmVjdCBjbGFzcz0ic3RhaXJzIiB4PSI2ODgiIHk9IjM5NSIgd2lkdGg9IjI0IiBoZWlnaHQ9IjkzIi8+CjxyZWN0IGNsYXNzPSJzdGFpcnMiIHg9IjY2NCIgeT0iMzk1IiB3aWR0aD0iMjQiIGhlaWdodD0iOTMiLz4KPHJlY3QgY2xhc3M9InN0YWlycyIgeD0iNjQwIiB5PSIzOTUiIHdpZHRoPSIyNCIgaGVpZ2h0PSI5MyIvPgo8cmVjdCBjbGFzcz0ic3RhaXJzIiB4PSI1NDciIHk9IjM5NSIgd2lkdGg9IjkzIiBoZWlnaHQ9IjkzIi8+CjxyZWN0IGNsYXNzPSJzdGFpcnMiIHg9IjU0NyIgeT0iNDg4IiB3aWR0aD0iOTMiIGhlaWdodD0iMjQiLz4KPHJlY3QgY2xhc3M9InN0YWlycyIgeD0iNTQ3IiB5PSI1MTIiIHdpZHRoPSI5MyIgaGVpZ2h0PSIyNCIvPgo8cmVjdCBjbGFzcz0ic3RhaXJzIiB4PSI1NDciIHk9IjUzNSIgd2lkdGg9IjkzIiBoZWlnaHQ9IjI0Ii8+CjxyZWN0IGNsYXNzPSJzdGFpcnMiIHg9IjU0NyIgeT0iNTU5IiB3aWR0aD0iOTMiIGhlaWdodD0iMjQiLz4KPC9zdmc+',
                    'pixelsPerMeter' => 0,
                    'north' => 0
                ],
                [
                    'id' => '2',
                    'level' => 0,
                    'name' => 'Erster Stock',
                    'type' => 'UpperFloor',
                    'map' => 'PHN2ZyB2ZXJzaW9uPSIxLjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiCiB2aWV3Qm94PSIwIDAgMTM0MCA4MjAiPgo8cmVjdCBpZD0ibWFwX3Jvb21fOSIgY2xhc3M9InJvb20iIHg9IjU0NiIgeT0iMjYyIiB3aWR0aD0iMjYwIiBoZWlnaHQ9IjU1NyIvPgo8cG9seWdvbiBpZD0ibWFwX3Jvb21fMTAiIGNsYXNzPSJyb29tIiBwb2ludHM9IjU0NiwyNjIgNTI1LDI2MiA1MjUsNzQgMjQ3LDc0IDI0Nyw0MTMgNTQ2LDQxMyAiLz4KPHJlY3QgaWQ9Im1hcF9yb29tXzExIiBjbGFzcz0icm9vbSIgeD0iNTI1IiB5PSIwIiB3aWR0aD0iMjkyIiBoZWlnaHQ9IjI2MiIvPgo8cmVjdCBpZD0ibWFwX3Jvb21fMTMiIGNsYXNzPSJyb29tIiB4PSI4MDYiIHk9IjQ4NSIgd2lkdGg9IjI4OSIgaGVpZ2h0PSIyNjgiLz4KPHJlY3QgaWQ9Im1hcF9yb29tXzE0IiBjbGFzcz0icm9vbSIgeD0iMjQ3IiB5PSI0MTMiIHdpZHRoPSIyOTkiIGhlaWdodD0iMzM5Ii8+Cjxwb2x5Z29uIGlkPSJtYXBfcm9vbV8xMiIgY2xhc3M9InJvb20iIHBvaW50cz0iODE3LDc0IDgxNywyNjIgODA2LDI2MiA4MDYsNDg1IDEwOTUsNDg1IDEwOTUsNzQgIi8+CiAgCjxwYXRoIGNsYXNzPSJ3YWxsIiBkPSJNMjQ3LDc0IEg1MjUgVjAgaDI5MiBWNzQgaDI3OCB2Njc5IEg4MDYgdjY2IEg1NDYgdi02NkgyNDcgVjc0eiIvPgo8cGF0aCBjbGFzcz0id2FsbCIgZD0iTTUyNSw3NCB2MTg4IGgyMSB2NDkwIi8+CjxwYXRoIGNsYXNzPSJ3YWxsIiBkPSJNODE3LDc0IHYxODggaC0xMSB2NDkwIi8+CjxsaW5lIGNsYXNzPSJ3YWxsIiB4MT0iNTQ2IiB5MT0iMjYyIiB4Mj0iODA2IiB5Mj0iMjYyIi8+CjxsaW5lIGNsYXNzPSJ3YWxsIiB4MT0iMTAxOCIgeTE9IjM0NyIgeDI9IjgwNiIgeTI9IjM0NyIvPgo8bGluZSBjbGFzcz0id2FsbCIgeDE9IjgwNiIgeTE9IjQ4NSIgeDI9IjEwOTUiIHkyPSI0ODUiLz4KPGxpbmUgY2xhc3M9IndhbGwiIHgxPSIyNDciIHkxPSI0MTMiIHgyPSI1NDYiIHkyPSI0MTMiLz4KICAKPGxpbmUgY2xhc3M9IndpbmRvdyIgeDE9IjU4MSIgeTE9IjAiIHgyPSI3ODIiIHkyPSIwIi8+CjxsaW5lIGNsYXNzPSJ3aW5kb3ciIHgxPSIyNDciIHkxPSI0NzciIHgyPSIyNDciIHkyPSI2MDAiLz4KPGxpbmUgY2xhc3M9IndpbmRvdyIgeDE9IjEwOTUiIHkxPSI1MTkiIHgyPSIxMDk1IiB5Mj0iNjM2Ii8+CjxsaW5lIGNsYXNzPSJ3aW5kb3ciIHgxPSIxMDk1IiB5MT0iMTk5IiB4Mj0iMTA5NSIgeTI9IjMxNSIvPgo8bGluZSBjbGFzcz0id2luZG93IiB4MT0iMTA5NSIgeTE9IjM4OSIgeDI9IjEwOTUiIHkyPSI0NDUiLz4KPGxpbmUgY2xhc3M9IndpbmRvdyIgeDE9IjU5NyIgeTE9IjgxOSIgeDI9Ijc2NCIgeTI9IjgxOSIvPgo8bGluZSBjbGFzcz0id2luZG93IiB4MT0iMjQ3IiB5MT0iMjQ0IiB4Mj0iMjQ3IiB5Mj0iMzU1Ii8+CiAgCjxsaW5lIGNsYXNzPSJkb29yIiB4MT0iODA2IiB5MT0iNTMwIiB4Mj0iODA2IiB5Mj0iNjAxIi8+CjxsaW5lIGNsYXNzPSJkb29yIiB4MT0iNTQ2IiB5MT0iNjU3IiB4Mj0iNTQ2IiB5Mj0iNzIxIi8+CjxsaW5lIGNsYXNzPSJkb29yIiB4MT0iNTQ2IiB5MT0iMjcwIiB4Mj0iNTQ2IiB5Mj0iMzM2Ii8+CjxsaW5lIGNsYXNzPSJkb29yIiB4MT0iODA2IiB5MT0iMjcwIiB4Mj0iODA2IiB5Mj0iMzM0Ii8+CjxsaW5lIGNsYXNzPSJkb29yIiB4MT0iNTgxIiB5MT0iMjYyIiB4Mj0iNjQ0IiB5Mj0iMjYyIi8+CjxsaW5lIGNsYXNzPSJkb29yIiB4MT0iMTAyNiIgeTE9IjQ4NSIgeDI9IjEwODQiIHkyPSI0ODUiLz4KICAKPHJlY3QgY2xhc3M9InN0YWlycyIgeD0iNjg4IiB5PSIzOTUiIHdpZHRoPSIyNCIgaGVpZ2h0PSI5MyIvPgo8cmVjdCBjbGFzcz0ic3RhaXJzIiB4PSI2NjQiIHk9IjM5NSIgd2lkdGg9IjI0IiBoZWlnaHQ9IjkzIi8+CjxyZWN0IGNsYXNzPSJzdGFpcnMiIHg9IjY0MCIgeT0iMzk1IiB3aWR0aD0iMjQiIGhlaWdodD0iOTMiLz4KPHJlY3QgY2xhc3M9InN0YWlycyIgeD0iNTQ3IiB5PSIzOTUiIHdpZHRoPSI5MyIgaGVpZ2h0PSI5MyIvPgo8cmVjdCBjbGFzcz0ic3RhaXJzIiB4PSI1NDciIHk9IjQ4OCIgd2lkdGg9IjkzIiBoZWlnaHQ9IjI0Ii8+CjxyZWN0IGNsYXNzPSJzdGFpcnMiIHg9IjU0NyIgeT0iNTEyIiB3aWR0aD0iOTMiIGhlaWdodD0iMjQiLz4KPHJlY3QgY2xhc3M9InN0YWlycyIgeD0iNTQ3IiB5PSI1MzUiIHdpZHRoPSI5MyIgaGVpZ2h0PSIyNCIvPgo8cmVjdCBjbGFzcz0ic3RhaXJzIiB4PSI1NDciIHk9IjU1OSIgd2lkdGg9IjkzIiBoZWlnaHQ9IjI0Ii8+Cjwvc3ZnPgo=',
                    'pixelsPerMeter' => 0,
                    'north' => 0
                ]
            ]),
            'Rooms' => json_encode([
                [
                    'id' => '3',
                    'floor' => 1,
                    'name' => 'Diele',
                    'type' => 'Misc',
                    'presence' => 0
                ],
                [
                    'id' => '4',
                    'floor' => 1,
                    'name' => 'Büro',
                    'type' => 'WorkRoom',
                    'presence' => 0
                ],
                [
                    'id' => '5',
                    'floor' => 1,
                    'name' => 'Wohnen/Essen',
                    'type' => 'LivingRoom',
                    'presence' => 0
                ],
                [
                    'id' => '6',
                    'floor' => 1,
                    'name' => 'Vorrat',
                    'type' => 'StoreRoom',
                    'presence' => 0
                ],
                [
                    'id' => '7',
                    'floor' => 1,
                    'name' => 'DU/WC',
                    'type' => 'BathRoom',
                    'presence' => 0
                ],
                [
                    'id' => '8',
                    'floor' => 1,
                    'name' => 'HWR',
                    'type' => 'WorkRoom',
                    'presence' => 0
                ],
                [
                    'id' => '9',
                    'floor' => 2,
                    'name' => 'Galerie',
                    'type' => 'Misc',
                    'presence' => 0
                ],
                [
                    'id' => '10',
                    'floor' => 2,
                    'name' => 'Arbeiten 1',
                    'type' => 'WorkRoom',
                    'presence' => 0
                ],
                [
                    'id' => '11',
                    'floor' => 2,
                    'name' => 'Arbeiten 2',
                    'type' => 'WorkRoom',
                    'presence' => 0
                ],
                [
                    'id' => '12',
                    'floor' => 2,
                    'name' => 'Zimmer/Besprechung',
                    'type' => 'WorkRoom',
                    'presence' => 0
                ],
                [
                    'id' => '13',
                    'floor' => 2,
                    'name' => 'Schlafen/Ankleide',
                    'type' => 'BedRoom',
                    'presence' => 0
                ],
                [
                    'id' => '14',
                    'floor' => 2,
                    'name' => 'Bad',
                    'type' => 'BathRoom',
                    'presence' => 0
                ]
            ]),
            'SmokeDetectors' => '[]',
            'TemperatureSensors' => '[]',
            'Doors' => '[]'
        ]));
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

        $nextPersonID = 100000;
        foreach (json_decode($this->ReadPropertyString('Rooms'), true) as $room) {
            if ($room['presence'] != 0 && GetValue($room['presence'])) {
                $persons[] = [
                    'id' => $nextPersonID,
                    'coreData' => false
                ];
                $nextPersonID++;
            }
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
            unset($room['presence']);
            $result[] = $room;
        }

        return $result;
    }

    private function GetObjectListDevices() {
        $result = [];

        foreach (json_decode($this->ReadPropertyString('SmokeDetectors'), true) as $smokeDetector) {
            $result[] = $this->ComputeDeviceInformation($smokeDetector, 'SmokeDetector', false);
        }

        foreach (json_decode($this->ReadPropertyString('TemperatureSensors'), true) as $temperatureSensor) {
            $result[] = $this->ComputeDeviceInformation($temperatureSensor, 'TemperatureSensor', false);
        }

        foreach (json_decode($this->ReadPropertyString('Doors'), true) as $door) {
            $variable = IPS_GetVariable($door['variableID']);
            $switchable = ($variable['VariableCustomAction'] > 10000) || ($variable['VariableAction'] > 10000);
            $result[] = $this->ComputeDeviceInformation($door, 'Door', $switchable);
        }

        foreach (json_decode($this->ReadPropertyString('Lights'), true) as $light) {
            $variable = IPS_GetVariable($light['variableID']);
            $switchable = ($variable['VariableCustomAction'] > 10000) || ($variable['VariableAction'] > 10000);
            $result[] = $this->ComputeDeviceInformation($light, 'Light', $switchable);
        }

        foreach (json_decode($this->ReadPropertyString('EmergencyOff'), true) as $emergencyOff) {
            $variable = IPS_GetVariable($emergencyOff['variableID']);
            $switchable = ($variable['VariableCustomAction'] > 10000) || ($variable['VariableAction'] > 10000);
            $result[] = $this->ComputeDeviceInformation($emergencyOff, 'EmergencyOff', $switchable);
        }

        foreach (json_decode($this->ReadPropertyString('Shutters'), true) as $shutter) {
            $variable = IPS_GetVariable($shutter['variableID']);
            $switchable = ($variable['VariableCustomAction'] > 10000) || ($variable['VariableAction'] > 10000);
            $result[] = $this->ComputeDeviceInformation($shutter, 'Shutter', $switchable);
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
                'map' => $floor['map'],
                'pixelsPerMeter' => $floor['pixelsPerMeter'],
                'north' => $floor['north']
            ];
        }

        return $maps;
    }

    private function ComputeStatus($ids) {
        $persons = [];
        foreach (json_decode($this->ReadPropertyString('Persons'), true) as $person) {
            if ((sizeof($ids) == 0) || in_array(intval($person['id']), $ids)) {
                $persons[] = [
                    'id' => intval($person['id']),
                    'present' => 'Unknown'
                ];
            }
        }

        $rooms = [];
        $nextPersonID = 100000;
        foreach (json_decode($this->ReadPropertyString('Rooms'), true) as $room) {
            if ((sizeof($ids) == 0) || in_array(intval($room['id']), $ids)) {
                $rooms[] = [
                    'id' => intval($room['id']),
                    'status' => $this->ComputeStatusOfRoom($room['id'])
                ];
            }

            if ($room['presence'] != 0 && GetValue($room['presence'])) {
                $persons[] = [
                    'id' => $nextPersonID,
                    'present' => 'Present',
                    'likelyPositions' => [
                        'room' => intval($room['id']),
                        'probability' => self::INITIAL_PROBABILITY_MOTION
                    ]
                ];
                $nextPersonID++;
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

        foreach (json_decode($this->ReadPropertyString('Lights'), true) as $light) {
            if ((sizeof($ids) == 0) || in_array(intval($light['id']), $ids)) {
                $devices[] = [
                    'id' => intval($light['id']),
                    'lastUpdate' => IPS_GetVariable($light['variableID'])['VariableUpdated'],
                    'lastChange' => IPS_GetVariable($light['variableID'])['VariableChanged'],
                    'value' => [
                        'on' => GetValueBoolean($light['variableID'])
                    ]
                ];
            }
        }

        foreach (json_decode($this->ReadPropertyString('EmergencyOff'), true) as $emergencyOff) {
            if ((sizeof($ids) == 0) || in_array(intval($emergencyOff['id']), $ids)) {
                $devices[] = [
                    'id' => intval($emergencyOff['id']),
                    'lastUpdate' => IPS_GetVariable($emergencyOff['variableID'])['VariableUpdated'],
                    'lastChange' => IPS_GetVariable($emergencyOff['variableID'])['VariableChanged'],
                    'value' => [
                        'active' => GetValueBoolean($emergencyOff['variableID'])
                    ]
                ];
            }
        }

        foreach (json_decode($this->ReadPropertyString('Shutters'), true) as $shutter) {
            if ((sizeof($ids) == 0) || in_array(intval($shutter['id']), $ids)) {
                $devices[] = [
                    'id' => intval($shutter['id']),
                    'lastUpdate' => IPS_GetVariable($shutter['variableID'])['VariableUpdated'],
                    'lastChange' => IPS_GetVariable($shutter['variableID'])['VariableChanged'],
                    'value' => [
                        'shutterPosition' => floatval(self::getDimValue($shutter['variableID'])) * 0.01
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
        $alarmTypes = json_decode($this->ReadAttributeString('AlarmTypes'), true);

        $result = [
            'alarm' => (sizeof($alarmTypes) > 0)
        ];
        if (sizeof($alarmTypes) > 0) {
            $result['types'] = $alarmTypes;
            $presenceVariableID = $this->ReadPropertyInteger('PresenceGeneral');
            if ($presenceVariableID != 0) {
                if (GetValue($presenceVariableID)) {
                    $result['lastPresence'] = time();
                }
                else {
                    $result['lastPresence'] = IPS_GetVariable($presenceVariableID)['VariableChanged'];
                }
            }
        }
        return $result;
    }

    private function SwitchVariable($irisID, $value) {
        $variableID = $this->GetVariableIDByIRISID($irisID);
        $type = $this->GetDeviceTypeByIRISID($irisID);

        switch ($type) {
            case "Door":
            case "Light":
            case "EmergencyOff":
                return self::switchDevice($variableID, $value);

            case "Shutter":
                return self::dimDevice($variableID, $value * 100);
        }

        return false;
    }

    private function GetVariableIDByIRISID($irisID) {
        foreach (["SmokeDetectors", "Doors", "Lights", "EmergencyOff", "Shutters"] as $property) {
            foreach (json_decode($this->ReadPropertyString($property), true) as $value) {
                if (intval($value['id']) == $irisID) {
                    return $value['variableID'];
                }
            }
        }

        $this->SendDebug("IRiS - Error", "Variable for IRiS ID does not exist", 0);
        return 0;
    }

    private function GetDeviceTypeByIRISID($irisID) {
        foreach (["SmokeDetectors", "Doors", "Lights", "EmergencyOff", "Shutters"] as $property) {
            foreach (json_decode($this->ReadPropertyString($property), true) as $value) {
                if (intval($value['id']) == $irisID) {
                    switch ($property) {
                        case "SmokeDetectors":
                            return "SmokeDetector";

                        case "Doors":
                            return "Door";

                        case "Lights":
                            return "Light";

                        case "EmergencyOff":
                            return "EmergencyOff";

                        case "Shutters":
                            return "Shutter";
                    }
                }
            }
        }

        $this->SendDebug("IRiS - Error", "Device Type for IRiS ID does not exist", 0);
        return "Invalid";
    }

    private function FillIDs() {
        $availableID = 0;
        $propertyNames = ["Floors", "Rooms", "Persons", "SmokeDetectors", "TemperatureSensors", "Doors", "Lights", "EmergencyOff", "Shutters"];
        foreach($propertyNames as $property) {
            foreach (json_decode($this->ReadPropertyString($property), true) as $value) {
                if ($value['id'] != '') {
                    $availableID = max($availableID, intval($value['id']));
                }
            }
        }
        $availableID++; // AvailableID is now one more than the highest ID

        $changed = false;
        foreach($propertyNames as $property) {
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
}

?>