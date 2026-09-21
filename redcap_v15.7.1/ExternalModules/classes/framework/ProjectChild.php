<?php
namespace ExternalModules;

abstract class ProjectChild
{
    private $project;
    private $name;

    function __construct($project, $name){
        $this->project = $project;
        $this->name = $name;
    }

    function getProject(){
        return $this->project;
    }

    function getName(){
        return $this->name;
    }
}