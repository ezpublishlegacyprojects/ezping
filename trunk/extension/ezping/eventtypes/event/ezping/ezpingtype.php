<?php
// Created on: <15-Nov-2008 14:00 damien pobel>
//
// SOFTWARE NAME: eZ Ping
// SOFTWARE RELEASE: 1.0
// COPYRIGHT NOTICE: Copyright (C) 1999-2008 Damien POBEL
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.

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
        $ini = eZINI::instance( 'ezping.ini' );
        $userAgent = $ini->variable( 'PingSettings', 'UserAgent' );
        $oldUserAgent = ini_set( 'user_agent', $userAgent );
        $result = ( false !== @file_get_contents( $url ) );
        if ( $oldUserAgent !== false )
        {
            ini_set( 'user_agent', $oldUserAgent );
        }
        return $result;
    }
}

eZWorkflowEventType::registerEventType( eZPingType::TYPE_PING, 'ezpingtype' );


?>
