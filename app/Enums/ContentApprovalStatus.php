<?php

namespace App\Enums;

enum ContentApprovalStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case ARCHIVED = 'archived';
}
