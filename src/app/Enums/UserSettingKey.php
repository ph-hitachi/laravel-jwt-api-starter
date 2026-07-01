<?php

namespace App\Enums;

enum UserSettingKey: string
{
    case LOCATION_SHARING = 'location_sharing';
    case NEARBY = 'nearby';
    case SOUND_RECORDING = 'sound_recording';
    case SILENT_MODE = 'silent_mode';
}
