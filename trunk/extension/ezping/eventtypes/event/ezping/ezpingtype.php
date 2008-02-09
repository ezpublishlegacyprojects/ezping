<?php


class eZPingEvent extends eZWorkflowEventType
{
	const EZPING_ID = 'ezpingevent';

	function eZPingEvent()
	{
		$this->eZWorkflowEventType( self::EZPING_ID, ezi18n( 'kernel/workflow/event', 'Ping' ) );
		$this->setTriggerTypes( array( 'content' => array( 'publish' => array( 'after' ) ) ) );
	}


	function execute( $process, $event )
	{
		eZDebug::writeDebug( 'Executing ' . self::EZPING_ID . ' event' );
		$parameters = $process->attribute( 'parameter_list' );

		$object = eZContentObject::fetch( $parameters['object_id'] );
		if ( !is_object( $object ) )
			return eZWorkflowType::STATUS_WORKFLOW_CANCELLED;

		$ini = eZINI::instance( 'ezping.ini' );

		// check content class of object
		$pingClassesList = $ini->variable( 'PingSettings', 'PingClasses' );
		$classIdentifier = $object->attribute( 'class_identifier' );
		if ( !in_array( $classIdentifier, $pingClassesList ) )
			return eZWorkflowEventType::STATUS_ACCEPTED;

		// check cronjob use
		$useCronjob = $ini->variable( 'PingSettings', 'UseCronjob' ) == 'enabled';
		if ( $useCronjob && !isset( $parameters['use_cronjob'] ) )
		{
			$parameters['use_cronjob'] = true;
			$process->setParameters( $parameters );
			return eZWorkflowEventType::STATUS_DEFERRED_TO_CRON;
		}

		$urlList = $ini->variable( $classIdentifier, 'PingURL' );
		if ( !$urlList || !is_array( $urlList ) || empty( $urlList ) )
		{
			eZDebug::writeError( 'ezping.ini not valid, no PingURL for ' . $classIdentifier );
			return eZWorkflowEventType::STATUS_ACCEPTED;
		}
		foreach( $urlList as $url )
		{
			$res = ' KO';
			if ( self::ping( $url ) )
				$res = ' OK';
			eZDebug::writeNotice( 'Pinging ' . $url . $res );
		}
		return eZWorkflowEventType::STATUS_ACCEPTED;
	}


	static function ping( $url )
	{
		return ( file_get_contents( $url ) !== false );
	}



}









?>
