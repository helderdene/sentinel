<?php

namespace App\Enums;

enum FrasAccessSubject: string
{
    case RecognitionEventFace = 'recognition_event_face';
    case RecognitionEventScene = 'recognition_event_scene';
    case PersonnelPhoto = 'personnel_photo';
}
