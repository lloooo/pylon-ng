<?php
include "pylon/pylon.php" ;
XSetting::$logMode   = XSetting::LOG_DEBUG_MODE ;
XSetting::$prjName   = "%{PRJ_NAME}" ;
XSetting::$logTag    = XSetting::ensureEnv("USER") ;
XSetting::$runPath   = XSetting::ensureEnv("RUN_PATH") ;
XSetting::$bootstrap = "pylonstrap.php" ;
XPylon::useEnv() ;
