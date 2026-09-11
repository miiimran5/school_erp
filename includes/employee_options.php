<?php

$employee_departments = array(
    'Teaching',
    'Non Teaching',
    'Administrative'
);

$employee_designations = array(
    'Teaching' => array(
        'PGT',
        'TGT',
        'PRT',
        'NTT'
    ),
    'Non Teaching' => array(
        'Receptionist',
        'Accountant',
        'Fee Clerk'
    ),
    'Administrative' => array(
        'Chairman',
        'Director',
        'Director Sports',
        'Principal',
        'Vice-Principal'
    )
);

function employee_option_is_valid($department, $designation)
{
    global $employee_designations;

    return isset($employee_designations[$department])
        && in_array($designation, $employee_designations[$department], true);
}

function employee_custom_designation_is_valid($department, $designation)
{
    global $employee_departments;

    return in_array($department, $employee_departments, true)
        && is_string($designation)
        && trim($designation) !== ''
        && strlen(trim($designation)) <= 100;
}
