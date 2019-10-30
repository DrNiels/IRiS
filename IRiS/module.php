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
        $this->RegisterPropertyString("Windows", "[]");
        $this->RegisterPropertyString("Lights", "[]");
        $this->RegisterPropertyString("EmergencyOff", "[]");
        $this->RegisterPropertyString("Shutters", "[]");
        $this->RegisterPropertyString("SwitchesButtons", "[]");
        $this->RegisterPropertyString("MotionSensors", "[]");
        $this->RegisterPropertyString("Cameras", "[]");
        $this->RegisterPropertyString("Images", "[]");

        $this->RegisterPropertyBoolean("AutomaticReactionOpenShuttersActivate", true);
        $this->RegisterPropertyString("AutomaticReactionOpenShuttersExceptions", "[]");
        $this->RegisterPropertyBoolean("AutomaticReactionOpenDoorsActivate", true);
        $this->RegisterPropertyString("AutomaticReactionOpenDoorsExceptions", "[]");
        $this->RegisterPropertyBoolean("AutomaticReactionLightsOnActivate", true);
        $this->RegisterPropertyString("AutomaticReactionLightsOnExceptions", "[]");
        $this->RegisterPropertyBoolean("AutomaticReactionSealFireActivate", false);

        $this->RegisterAttributeString("AlarmTypes", "[]");

        $this->RegisterTimer('MarkRoomsTimer', 1000, 'IRIS_UpdateMarkedRooms($_IPS["TARGET"]);');
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
                        fill: #ffffff;
                        fill-opacity: 0.6;
                    }
                    
                    .wall {
                        stroke: black;
                        stroke-width: 5;
                        fill: none;
                    }
                    
                    .door {
                        stroke: #ffffff;
                        stroke-width: 8;
                    }
                    
                    .window {
                        stroke: #ffffff;
                        stroke-width: 3;
                    }
                    
                    .stairs {
                        fill: #008351;
                        stroke: #000000;
                    }
                    
                    .background {
                        fill: #dddddd;
                    }
                    </style>
                    <rect class="background" height="100%" width="100%" x="0" y="0"/>' .
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

        $shutterOptions = [];
        $shutters = json_decode($this->ReadPropertyString('Shutters'), true);
        foreach($shutters as $shutter) {
            $shutterOptions[] = [
                'caption' => IPS_GetLocation($shutter['variableID']),
                'value' => $shutter['variableID']
            ];
        }
        $shutterAdd = 0;
        if (sizeof($shutterOptions) > 0) {
            $shutterAdd = $shutterOptions[0]['value'];
        }

        $switchableDoorOptions = [];
        $doors = json_decode($this->ReadPropertyString('Doors'), true);
        foreach($doors as $door) {
            if (self::getSwitchCompatibility($door['variableID']) == 'OK') {
                $switchableDoorOptions[] = [
                    'caption' => IPS_GetLocation($door['variableID']),
                    'value' => $door['variableID']
                ];
            }
        }
        $switchableDoorAdd = 0;
        if (sizeof($switchableDoorOptions) > 0) {
            $switchableDoorAdd = $switchableDoorOptions[0]['value'];
        }

        $lightOptions = [];
        $lights = json_decode($this->ReadPropertyString('Lights'), true);
        foreach($lights as $light) {
            $lightOptions[] = [
                'caption' => IPS_GetLocation($light['variableID']),
                'value' => $light['variableID']
            ];
        }
        $lightAdd = 0;
        if (sizeof($lightOptions) > 0) {
            $lightAdd = $lightOptions[0]['value'];
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
                            'visible' => false,
                            'edit' => [
                                'type' => 'NumberSpinner',
                                'digits' => 2
                            ]
                        ],
                        [
                            'caption' => 'Map: North Degree',
                            'name' => 'north',
                            'width' => '200px',
                            'add' => 0,
                            'visible' => false,
                            'edit' => [
                                'type' => 'NumberSpinner',
                                'digits' => 2,
                                'suffix' => '°'
                            ]
                        ],
                        [
                            'caption' => 'Map: World Coordinate',
                            'name' => 'coordinates',
                            'width' => '200px',
                            'visible' => false,
                            'add' => '{ "latitude": 0, "longitude": 0 }',
                            'edit' => [
                                'type' => 'SelectLocation'
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
                                        'caption' => 'Hall',
                                        'value' => 'Hall'
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
                            'caption' => 'Image',
                            'name' => 'imageID',
                            'width' => '200px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'SelectMedia'
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
                            'caption' => 'Device Image',
                            'name' => 'imageID',
                            'width' => '200px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'SelectMedia'
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
                            'caption' => 'Device Image',
                            'name' => 'imageID',
                            'width' => '200px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'SelectMedia'
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
                            'caption' => 'Connecting Room',
                            'name' => 'room2',
                            'width' => '200px',
                            'add' => -1,
                            'edit' => [
                                'type' => 'Select',
                                'options' => array_merge([[
                                    'caption' => 'No room',
                                    'value' => -1
                                ]], $roomOptions)
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
                            'caption' => 'Device Image',
                            'name' => 'imageID',
                            'width' => '200px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'SelectMedia'
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
                    'name' => 'Windows',
                    'rowCount' => 10,
                    'caption' => 'Windows',
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
                            'caption' => 'Device Image',
                            'name' => 'imageID',
                            'width' => '200px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'SelectMedia'
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
                            'caption' => 'Blink when marked',
                            'name' => 'blink',
                            'width' => '200px',
                            'add' => false,
                            'edit' => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'caption' => 'Device Image',
                            'name' => 'imageID',
                            'width' => '200px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'SelectMedia'
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
                            'caption' => 'Device Image',
                            'name' => 'imageID',
                            'width' => '200px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'SelectMedia'
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
                            'caption' => 'Device Image',
                            'name' => 'imageID',
                            'width' => '200px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'SelectMedia'
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
                    'name' => 'SwitchesButtons',
                    'rowCount' => 10,
                    'caption' => 'Switches/Buttons',
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
                            'caption' => 'Device Image',
                            'name' => 'imageID',
                            'width' => '200px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'SelectMedia'
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
                            'caption' => 'Device Image',
                            'name' => 'imageID',
                            'width' => '200px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'SelectMedia'
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
                    'name' => 'Cameras',
                    'rowCount' => 10,
                    'caption' => 'Cameras',
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
                            'caption' => 'Camera Image',
                            'name' => 'mediaID',
                            'width' => 'auto',
                            'add' => 0,
                            'edit' => [
                                'type' => 'SelectMedia'
                            ]
                        ],
                        [
                            'caption' => 'Device Image',
                            'name' => 'imageID',
                            'width' => '200px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'SelectMedia'
                            ]
                        ],
                        [
                            'caption' => 'Direction',
                            'name' => 'direction',
                            'width' => '100px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'NumberSpinner',
                                'digits' => 2,
                                'suffix' => '°'
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
                    'name' => 'Images',
                    'rowCount' => 10,
                    'caption' => 'Static Images',
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
                            'caption' => 'Image',
                            'name' => 'mediaID',
                            'width' => 'auto',
                            'add' => 0,
                            'edit' => [
                                'type' => 'SelectMedia'
                            ]
                        ],
                        [
                            'caption' => 'Direction',
                            'name' => 'direction',
                            'width' => '100px',
                            'add' => 0,
                            'edit' => [
                                'type' => 'NumberSpinner',
                                'digits' => 2,
                                'suffix' => '°'
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
                    'type' => 'ExpansionPanel',
                    'caption' => 'Automatic Reaction on Alert',
                    'items' => [
                        [
                            'type' => 'RowLayout',
                            'items' => [
                                [
                                    'type' => 'CheckBox',
                                    'name' => 'AutomaticReactionOpenShuttersActivate',
                                    'caption' => 'Open All Shutters'
                                ],
                                [
                                    'type' => 'PopupButton',
                                    'caption' => 'Exceptions',
                                    'popup' => [
                                        'caption' => 'Shutters that should not open',
                                        'items' => [
                                            [
                                                'type' => 'List',
                                                'name' => 'AutomaticReactionOpenShuttersExceptions',
                                                'add' => true,
                                                'delete' => true,
                                                'rowCount' => 3,
                                                'columns' => [
                                                    [
                                                        'caption' => 'Shutter',
                                                        'name' => 'variableID',
                                                        'width' => 'auto',
                                                        'add' => $shutterAdd,
                                                        'edit' => [
                                                            'type' => 'Select',
                                                            'options' => $shutterOptions
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        [
                            'type' => 'RowLayout',
                            'items' => [
                                [
                                    'type' => 'CheckBox',
                                    'name' => 'AutomaticReactionOpenDoorsActivate',
                                    'caption' => 'Open Doors'
                                ],
                                [
                                    'type' => 'PopupButton',
                                    'caption' => 'Exceptions',
                                    'popup' => [
                                        'caption' => 'Doors that should not open',
                                        'items' => [
                                            [
                                                'type' => 'List',
                                                'name' => 'AutomaticReactionOpenDoorsExceptions',
                                                'add' => true,
                                                'delete' => true,
                                                'rowCount' => 3,
                                                'columns' => [
                                                    [
                                                        'caption' => 'Door',
                                                        'name' => 'variableID',
                                                        'width' => 'auto',
                                                        'add' => $switchableDoorAdd,
                                                        'edit' => [
                                                            'type' => 'Select',
                                                            'options' => $switchableDoorOptions
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        [
                            'type' => 'RowLayout',
                            'items' => [
                                [
                                    'type' => 'CheckBox',
                                    'name' => 'AutomaticReactionLightsOnActivate',
                                    'caption' => 'Switch Lights On'
                                ],
                                [
                                    'type' => 'PopupButton',
                                    'caption' => 'Exceptions',
                                    'popup' => [
                                        'caption' => 'Lights that should not be switched on',
                                        'items' => [
                                            [
                                                'type' => 'List',
                                                'name' => 'AutomaticReactionLightsOnExceptions',
                                                'add' => true,
                                                'delete' => true,
                                                'rowCount' => 3,
                                                'columns' => [
                                                    [
                                                        'caption' => 'Light',
                                                        'name' => 'variableID',
                                                        'width' => 'auto',
                                                        'add' => $lightAdd,
                                                        'edit' => [
                                                            'type' => 'Select',
                                                            'options' => $lightOptions
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        [
                            'type' => 'CheckBox',
                            'name' => 'AutomaticReactionSealFireActivate',
                            'caption' => 'Seal fire in rooms without persons (Caution: This option requires highly reliable presence detection or persons could be trapped in the burning room)'
                        ]
                    ]
                ]
            ])
        ]);
    }

    public function AddAlarm(string $alarmType) {
        $currentAlarmTypes = json_decode($this->ReadAttributeString('AlarmTypes'), true);

        // First alert! Activate Automatic Reaction!
        if (count($currentAlarmTypes) == 0) {
            $this->ExecuteAutomaticReaction();
        }

        if (!in_array($alarmType, $currentAlarmTypes)) {
            $currentAlarmTypes[] = $alarmType;
            $this->WriteAttributeString('AlarmTypes', json_encode($currentAlarmTypes));
        }
    }

    public function ResetAlarm() {
        $this->WriteAttributeString('AlarmTypes', '[]');
    }

    public function UpdateMarkedRooms() {
        $this->SetTimerInterval('MarkRoomsTimer', 0);

        $markedRooms = [];
        foreach (json_decode($this->ReadPropertyString('Rooms'), true) as $room) {
            $bufferContent = $this->GetBuffer('RoomMarked.' . $room['id']);
            $currentMarked = $bufferContent ? json_decode($bufferContent) : false;
            if ($currentMarked) {
                $markedRooms[] = intval($room['id']);
            }
        }
        

        $currentLight = false;
        $currentLightDetermined = false;
        
        foreach (json_decode($this->ReadPropertyString('Lights'), true) as $light) {
            if (in_array($light['room'], $markedRooms) && $light['blink']) {
                if (!$currentLightDetermined) {
                    $currentLight = GetValue($light['variableID']);
                    $currentLightDetermined = true;
                }

                self::switchDevice($light['variableID'], !$currentLight);
            }
        }

        $this->SetTimerInterval('MarkRoomsTimer', $currentLight ? 1000 : 2000);
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

            case 'resetAlarm':
                $this->ResetAlarm();
                $this->ReturnResult($request['id'], true);
                break;

            case 'getObjectImage':
                $this->ReturnResult($request['id'], IPS_GetMediaContent($this->GetObjectImageIDByIRISID($request['params']['id'])));
                break;

            case 'setRoomMarked':
                $this->SetRoomMarked($request['params']['id'], $request['params']['marked']);
                $this->ReturnResult($request['id'], true);
                break;

            default:
                $this->SendDebug("IRiS - Error", "Undefined method", 0);

        }
    }

    private function SetRoomMarked($roomID, $marked) {
        // Buffer could be empty
        $bufferContent = $this->GetBuffer("RoomMarked.$roomID");
        $currentMarked = $bufferContent ? json_decode($bufferContent) : false;
        
        // Only work here if something changes
        if ($currentMarked == $marked) {
            return;
        }

        $this->SetBuffer("RoomMarked.$roomID", json_encode($marked));

        if ($marked) {
            foreach (json_decode($this->ReadPropertyString('Lights'), true) as $light) {
                if (($light['room'] == $roomID) && $light['blink']) {
                    $this->SetBuffer('LightBeforeMarked.' . $light['id'], json_encode(GetValue($light['variableID'])));
                    $this->SendDebug('Save Light Before Marked', $light['id'] . ': ' . $this->GetBuffer('LightBeforeMarked.' . $light['id']), 0);
                }
            }
        }
        else {
            foreach (json_decode($this->ReadPropertyString('Lights'), true) as $light) {
                if (($light['room'] == $roomID) && $light['blink']) {
                    $this->SendDebug('Restore previous value', $light['id'] . '/' . $light['variableID'] . ': ' . $this->GetBuffer('LightBeforeMarked.' . $light['id']), 0);
                    self::switchDevice($light['variableID'], json_decode($this->GetBuffer('LightBeforeMarked.' . $light['id'])));
                }
            }
        }

    }

    private function ReturnResult($id, $result) {
        $response = json_encode([
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id
        ]);
        $this->SendDebug("IRiS - Response", $response, 0);
        header("Content-Length: " . strlen($response));
        header("Content-Type: application/json");
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
                array_walk($person['diseases'], function(&$item, $key) {
                    $item = trim($item);
                });
            }

            $person['coreData'] = true;
            $person['hasObjectImage'] = ($person['imageID'] > 0);
            unset($person['imageID']);

            $result[] = $person;
        }

        $nextPersonID = 100000;
        foreach (json_decode($this->ReadPropertyString('Rooms'), true) as $room) {
            if ($room['presence'] != 0 && GetValue($room['presence'])) {
                $result[] = [
                    'id' => $nextPersonID,
                    'coreData' => false,
                    'hasObjectImage' => false
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
            $switchable = (self::getSwitchCompatibility($door['variableID']) == 'OK');
            $result[] = $this->ComputeDeviceInformation($door, 'Door', $switchable);
        }

        foreach (json_decode($this->ReadPropertyString('Windows'), true) as $window) {
            $switchable = (self::getSwitchCompatibility($window['variableID']) == 'OK');
            $result[] = $this->ComputeDeviceInformation($window, 'Window', $switchable);
        }

        foreach (json_decode($this->ReadPropertyString('Lights'), true) as $light) {
            $switchable = (self::getSwitchCompatibility($light['variableID']) == 'OK');
            $result[] = $this->ComputeDeviceInformation($light, 'Light', $switchable);
        }

        foreach (json_decode($this->ReadPropertyString('EmergencyOff'), true) as $emergencyOff) {
            $switchable = (self::getSwitchCompatibility($emergencyOff['variableID']) == 'OK');
            $result[] = $this->ComputeDeviceInformation($emergencyOff, 'EmergencyOff', $switchable);
        }

        foreach (json_decode($this->ReadPropertyString('Shutters'), true) as $shutter) {
            $switchable = (self::getDimCompatibility($light['variableID']) == 'OK');
            $result[] = $this->ComputeDeviceInformation($shutter, 'Shutter', $switchable);
        }

        foreach (json_decode($this->ReadPropertyString('SwitchesButtons'), true) as $switchButton) {
            $result[] = $this->ComputeDeviceInformation($switchButton, 'SwitchButton', false);
        }

        foreach (json_decode($this->ReadPropertyString('MotionSensors'), true) as $motionSensor) {
            $result[] = $this->ComputeDeviceInformation($motionSensor, 'MotionSensor', false);
        }

        foreach (json_decode($this->ReadPropertyString('Cameras'), true) as $camera) {
            $result[] = $this->ComputeDeviceInformation($camera, 'Camera', false);
        }

        foreach (json_decode($this->ReadPropertyString('Images'), true) as $image) {
            $result[] = $this->ComputeDeviceInformation($image, 'Image', false);
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
        $result = [
            'id' => intval($value['id']),
            'room' => $value['room'],
            'position' => [
                'x' => $value['x'],
                'y' => $value['y']
            ],
            'type' => $type,
            'switchable' => $switchable,
            'hasObjectImage' => (isset($value['imageID']) && ($value['imageID'] != 0))
        ];

        if (in_array($type, ['Camera', 'Image'])) {
            $result['direction'] = $value['direction'];
        }

        return $result;
    }

    private function ComputeMaps() {
        $maps = [];
        foreach (json_decode($this->ReadPropertyString('Floors'), true) as $floor) {
            $maps[] = [
                'floor' => intval($floor['id']),
                'map' => $floor['map'],
                'pixelsPerMeter' => $floor['pixelsPerMeter'],
                'north' => $floor['north'],
                'coordinates' => json_decode($floor['coordinates'])
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
                $bufferContent = $this->GetBuffer('RoomMarked.' . $room['id']);
                $currentMarked = $bufferContent ? json_decode($bufferContent) : false;
                $rooms[] = [
                    'id' => intval($room['id']),
                    'status' => $this->ComputeStatusOfRoom($room['id']),
                    'lastPresence' => $this->GetLastPresence($room['presence']),
                    'marked' => $currentMarked
                ];
            }

            if ($room['presence'] != 0 && GetValue($room['presence'])) {
                $persons[] = [
                    'id' => $nextPersonID,
                    'present' => 'Present',
                    'likelyPositions' => [[
                        'room' => intval($room['id']),
                        'probability' => self::INITIAL_PROBABILITY_MOTION
                    ]]
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

        foreach (json_decode($this->ReadPropertyString('Windows'), true) as $window) {
            if ((sizeof($ids) == 0) || in_array(intval($window['id']), $ids)) {
                $devices[] = [
                    'id' => intval($window['id']),
                    'lastUpdate' => IPS_GetVariable($window['variableID'])['VariableUpdated'],
                    'lastChange' => IPS_GetVariable($window['variableID'])['VariableChanged'],
                    'value' => [
                        'open' => GetValueBoolean($window['variableID'])
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

        foreach (json_decode($this->ReadPropertyString('SwitchesButtons'), true) as $switchButton) {
            if ((sizeof($ids) == 0) || in_array(intval($switchButton['id']), $ids)) {
                $devices[] = [
                    'id' => intval($switchButton['id']),
                    'lastUpdate' => IPS_GetVariable($switchButton['variableID'])['VariableUpdated'],
                    'lastChange' => IPS_GetVariable($switchButton['variableID'])['VariableChanged'],
                    'value' => new stdClass()
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

        foreach (json_decode($this->ReadPropertyString('Cameras'), true) as $camera) {
            if ((sizeof($ids) == 0) || in_array(intval($camera['id']), $ids)) {
                $updated = IPS_MediaExists($camera['mediaID']) ? IPS_GetMedia($camera['mediaID'])['MediaUpdated'] : 0;
                $device = [
                    'id' => intval($camera['id']),
                    'lastUpdate' => $updated,
                    'lastChange' => $updated,
                    'value' => new stdClass
                ];

                if (sizeof($ids) > 0) {
                    $device['value']->image = IPS_MediaExists($camera['mediaID']) ? IPS_GetMediaContent($camera['mediaID']) : '';
                }

                $devices[] = $device;
            }
        }

        foreach (json_decode($this->ReadPropertyString('Images'), true) as $image) {
            if ((sizeof($ids) == 0) || in_array(intval($image['id']), $ids)) {
                $updated = IPS_MediaExists($image['mediaID']) ? IPS_GetMedia($image['mediaID'])['MediaUpdated'] : 0;
                $device = [
                    'id' => intval($image['id']),
                    'lastUpdate' => $updated,
                    'lastChange' => $updated,
                    'value' => new stdClass
                ];

                if (sizeof($ids) > 0) {
                    $device['value']->image = IPS_MediaExists($image['mediaID']) ? IPS_GetMediaContent($image['mediaID']) : '';
                }

                $devices[] = $device;
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

    private function GetLastPresence($variableID) {
        if ($variableID != 0) {
            if (GetValue($variableID)) {
                return time();
            }
            else {
                return IPS_GetVariable($variableID)['VariableChanged'];
            }
        }
        else {
            return 0;
        }
    }

    private function ComputeAlarm() {
        $alarmTypes = json_decode($this->ReadAttributeString('AlarmTypes'), true);

        $result = [
            'alarm' => (sizeof($alarmTypes) > 0)
        ];
        if (sizeof($alarmTypes) > 0) {
            $result['types'] = $alarmTypes;
            $result['lastPresence'] = $this->GetLastPresence($this->ReadPropertyInteger('PresenceGeneral'));
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
            case "Window":
                return self::switchDevice($variableID, $value);

            case "Shutter":
                return self::dimDevice($variableID, $value * 100);
        }

        return false;
    }

    private function GetVariableIDByIRISID($irisID) {
        foreach (["SmokeDetectors", "Doors", "Windows", "Lights", "EmergencyOff", "Shutters", "SwitchesButtons", "MotionSensors"] as $property) {
            foreach (json_decode($this->ReadPropertyString($property), true) as $value) {
                if (intval($value['id']) == $irisID) {
                    return $value['variableID'];
                }
            }
        }

        $this->SendDebug("IRiS - Error", "Variable for IRiS ID does not exist", 0);
        return 0;
    }

    private function GetMediaIDByIRISID($irisID) {
        foreach (["Cameras", "Images"] as $property) {
            foreach (json_decode($this->ReadPropertyString($property), true) as $value) {
                if (intval($value['id']) == $irisID) {
                    return $value['mediaID'];
                }
            }
        }

        $this->SendDebug("IRiS - Error", "Media for IRiS ID does not exist", 0);
        return 0;
    }

    private function GetObjectImageIDByIRISID($irisID) {
        foreach (["Persons", "SmokeDetectors", "Doors", "Windows", "Lights", "EmergencyOff", "Shutters", "SwitchesButtons", "MotionSensors", "Cameras"] as $property) {
            foreach (json_decode($this->ReadPropertyString($property), true) as $value) {
                if (intval($value['id']) == $irisID) {
                    return $value['imageID'];
                }
            }
        }

        $this->SendDebug("IRiS - Error", "Device Image for IRiS ID does not exist", 0);
        return 0;
    }

    private function GetDeviceTypeByIRISID($irisID) {
        foreach (["SmokeDetectors", "Doors", "Windows", "Lights", "EmergencyOff", "Shutters", "SwitchesButtons", "MotionSensors", "Cameras", "Images"] as $property) {
            foreach (json_decode($this->ReadPropertyString($property), true) as $value) {
                if (intval($value['id']) == $irisID) {
                    switch ($property) {
                        case "SmokeDetectors":
                            return "SmokeDetector";

                        case "Doors":
                            return "Door";

                        case "Windows":
                            return "Window";

                        case "Lights":
                            return "Light";

                        case "EmergencyOff":
                            return "EmergencyOff";

                        case "Shutters":
                            return "Shutter";

                        case "SwitchesButtons":
                            return "SwitchButton";

                        case "MotionSensors":
                            return "MotionSensor";

                        case "Cameras":
                            return "Camera";

                        case "Images":
                            return "Image";
                    }
                }
            }
        }

        $this->SendDebug("IRiS - Error", "Device Type for IRiS ID does not exist", 0);
        return "Invalid";
    }

    private function FillIDs() {
        $availableID = 0;
        $propertyNames = ["Floors", "Rooms", "Persons", "SmokeDetectors", "TemperatureSensors", "Doors", "Windows", "Lights", "EmergencyOff", "Shutters", "SwitchesButtons", "MotionSensors", "Cameras", "Images"];
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

    private function ExecuteAutomaticReaction() {
        if ($this->ReadPropertyBoolean('AutomaticReactionOpenShuttersActivate')) {
            $exceptions = [];
            foreach (json_decode($this->ReadPropertyString('AutomaticReactionOpenShuttersExceptions'), true) as $shutterExpection) {
                $exceptions[] = $shutterExpection['variableID'];
            }

            foreach (json_decode($this->ReadPropertyString('Shutters'), true) as $shutter) {
                if (!in_array($shutter['variableID'], $exceptions) && (self::getDimCompatibility($shutter['variableID']) == 'OK')) {
                    self::dimDevice($shutter['variableID'], 0);
                }
            }
        }

        if ($this->ReadPropertyBoolean('AutomaticReactionOpenDoorsActivate')) {
            $exceptions = [];
            foreach (json_decode($this->ReadPropertyString('AutomaticReactionOpenDoorsExceptions'), true) as $doorException) {
                $exceptions[] = $doorException['variableID'];
            }

            foreach (json_decode($this->ReadPropertyString('Doors'), true) as $door) {
                if (!in_array($door['variableID'], $exceptions) && (self::getSwitchCompatibility($door['variableID']) == 'OK')) {
                    self::switchDevice($door['variableID'], true);
                }
            }
        }

        if ($this->ReadPropertyBoolean('AutomaticReactionLightsOnActivate')) {
            $exceptions = [];
            foreach (json_decode($this->ReadPropertyString('AutomaticReactionLightsOnExceptions'), true) as $lightException) {
                $exceptions[] = $lightException['variableID'];
            }

            foreach (json_decode($this->ReadPropertyString('Lights'), true) as $light) {
                if (!in_array($light['variableID'], $exceptions) && (self::getSwitchCompatibility($light['variableID']) == 'OK')) {
                    self::switchDevice($light['variableID'], true);
                }
            }
        }

        if ($this->ReadPropertyBoolean('AutomaticReactionSealFireActivate')) {
            $this->SendDebug('Automatic Reaction - Seal Fire', 'Start', 0);
            foreach (json_decode($this->ReadPropertyString('Rooms'), true) as $room) {
                // Only seal if the room has presence detection and no person is currently detected and there is some alert status, i.e., Smoked or Burning
                // TODO: We could increase performance by first determining all rooms that should be sealed and only looping through windows and doors once afterwards
                if ($room['presence'] != 0 && !GetValue($room['presence']) && (sizeof($this->ComputeStatusOfRoom($room['id'])) > 0)) {
                    $this->SendDebug('Automatic Reaction - Seal Fire', 'Room should be sealed: ' . $room['id'], 0);
                    foreach (json_decode($this->ReadPropertyString('Doors'), true) as $door) {
                        if (in_array($room['id'], [ $door['room'], $door['room2']]) && (self::getSwitchCompatibility($door['variableID']) == 'OK')) {
                            $this->SendDebug('Automatic Reaction - Seal Fire', 'Close Door: ' . $door['id'] . '/' . $door['variableID'], 0);
                            self::switchDevice($door['variableID'], false);
                        }
                    }

                    foreach (json_decode($this->ReadPropertyString('Windows'), true) as $window) {
                        if (($window['room'] == $room['id']) && (self::getSwitchCompatibility($window['variableID']) == 'OK')) {
                            self::switchDevice($window['variableID'], false);
                        }
                    }
                }
            }
        }
    }
}

?>