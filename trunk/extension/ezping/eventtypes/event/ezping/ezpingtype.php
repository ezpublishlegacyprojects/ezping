<?php

class eZPingType extends eZWorkflowEventType
{
    const TYPE_PING = 'ezping';

    function eZPingType()
    {
        $this->eZWorkflowEventType( self::TYPE_PING, 'Ping' );
        $this->setTriggerTypes( array( 'content' => array( 'publish' => array( 'after' ) ) ) );
    }


    function execute( $process, $event )
    {
        eZDebug::writeDebug( 'Executing ' . __METHOD__ );
        $parameters = $process->attribute( 'parameter_list' );

        $object = eZContentObject::fetch( $parameters['object_id'] );
        if ( !is_object( $object ) )
        {
            return eZWorkflowType::STATUS_WORKFLOW_CANCELLED;
        }

        $ini = eZINI::instance( 'ezping.ini' );

        // check content class of object
        $pingClassesList = $ini->variable( 'PingSettings', 'PingClasses' );
        $classIdentifier = $object->attribute( 'class_identifier' );
        if ( !in_array( $classIdentifier, $pingClassesList ) )
        {
            return eZWorkflowEventType::STATUS_ACCEPTED;
        }

        // check cronjob use
        $useCronjob = $ini->variable( 'PingSettings', 'UseCronjob' ) == 'enabled';
        if ( $useCronjob && !isset( $parameters['use_cronjob'] ) )
        {
            $parameters['use_cronjob'] = true;
            $process->setParameters( $parameters );
            $process->store();
            return eZWorkflowEventType::STATUS_DEFERRED_TO_CRON_REPEAT;
        }
        $iniSection = $classIdentifier . 'PingSettings';

        $urlList = $ini->variable( $iniSection, 'PingURL' );
        if ( !$urlList || !is_array( $urlList ) || empty( $urlList ) )
        {
            eZDebug::writeError( 'ezping.ini not valid, no PingURL for ' . $classIdentifier );
            return eZWorkflowEventType::STATUS_ACCEPTED;
        }
        $logFile = $ini->variable( 'PingSettings', 'LogFile' );
        eZLog::write( 'Ping for ' . $object->attribute( 'name' ), $logFile );
        foreach( $urlList as $url )
        {
            $res = ' KO';
            if ( self::ping( $url ) )
            {
                $res = ' OK';
            }
            eZLog::write( '  ' . $url . $res, $logFile );
        }
        return eZWorkflowEventType::STATUS_ACCEPTED;
    }


    static function ping( $url )
    {
        return ( false !== @file_get_contents( $url ) );
    }
}

eZWorkflowEventType::registerEventType( eZPingType::TYPE_PING, 'ezpingtype' );


?>
