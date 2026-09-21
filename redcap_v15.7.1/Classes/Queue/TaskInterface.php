<?php
namespace Vanderbilt\REDCap\Classes\Queue;

interface TaskInterface {
    
    public function __construct($data);
    public function handle();

}