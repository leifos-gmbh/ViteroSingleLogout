<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/EventHandling/classes/class.ilEventHookPlugin.php';
require_once 'Services/Component/classes/class.ilPluginAdmin.php';

/**
 * @author Fabian Wolf <wolf@leifos.com>
 */
class ilViteroSingleLogoutPlugin extends ilEventHookPlugin
{
	const VIT_CTYPE = 'Services';
	const VIT_CNAME = 'Repository';
	const VIT_SLOT_ID = 'robj';
	const VIT_PNAME = 'Vitero';
	const VIT_MIN_VERSION = '1.6.0';

	/**
	 * @var ilViteroPlugin
	 */
	protected $vitero = null;

	/**
	 * @return bool
	 */
	final public function getPluginName()
	{
		return "ViteroSingleLogout";
	}

	public function handleEvent($a_component, $a_event, $a_params)
	{
		global $ilUser;
		switch($a_component)
		{
			case 'Services/Authentication':
				switch($a_event)
				{
					case 'afterLogout':
						if($this->initViteroPlugin())
						{
							if($vuser = $this->getViteroUserId($ilUser->getId()))
							{
								$sessions = $this->getUserSessions($vuser);

								$GLOBALS['ilLog']->write(__METHOD__.': Deleting session codes: '.var_export($sessions, $vuser));

								if(count($sessions) > 0)
								{
									$this->deleteSessions($sessions);
								}
							}
						}
					break;
				}
			break;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	protected function initViteroPlugin()
	{
		/**
		 * @var ilPluginAdmin $ilPluginAdmin
		 */
		global $ilPluginAdmin;

		if(!$ilPluginAdmin->isActive(self::VIT_CTYPE,self::VIT_CNAME, self::VIT_SLOT_ID,self::VIT_PNAME))
		{
			return false;
		}

		include_once './Services/Component/classes/class.ilPluginAdmin.php';

		$vit = ilPluginAdmin::getPluginObject(
			self::VIT_CTYPE,
			self::VIT_CNAME,
			self::VIT_SLOT_ID,
			self::VIT_PNAME
		);

		if(ilComponent::isVersionGreaterString(self::VIT_MIN_VERSION, $vit->getVersion()))
		{
			return false;
		}

		$this->vitero = $vit;
		return true;
	}


	/**
	 * @return ilViteroPlugin|null
	 */
	protected function getViteroInstance()
	{
		if(!($this->vitero instanceof ilViteroPlugin))
		{
			$this->initViteroPlugin();
		}
		return $this->vitero;
	}

	/**
	 * @param int $a_usr_id
	 * @return bool|int
	 */
	protected function getViteroUserId($a_usr_id)
	{
		$this->getViteroInstance()->includeClass('class.ilViteroUserMapping.php');
		$map = new ilViteroUserMapping();
		$vuser = $map->getVUserId($a_usr_id);

		return $vuser!= 0 ? $vuser : false;
	}

	/**
	 * @param integer $a_vitero_user_id
	 * @return string[]
	 */
	protected function getUserSessions($a_vitero_user_id)
	{
		$this->getViteroInstance()->includeClass('class.ilViteroSessionStore.php');

		return  ilViteroSessionStore::getInstance()->getSessionsByUser($a_vitero_user_id);
	}

	protected function deleteSessions($a_sessions)
	{

		$this->getViteroInstance()->includeClass('class.ilViteroSessionCodeSoapConnector.php');

		try
		{
			$connector = new ilViteroSessionCodeSoapConnector();
			$connector->deleteSessionCodes($a_sessions);
		}
		catch(ilViteroConnectorException $e)
		{
			$GLOBALS['ilLog']->write(__METHOD__.': ViteroSingleLogout failed with message: '.$e->getMessage());
		}
	}

	protected function beforeActivation()
	{
		if(!$this->initViteroPlugin())
		{
			ilUtil::sendFailure("You need atleast ILIAS Vitero Plugin version ". self::VIT_MIN_VERSION, true);
			return false;
		}
		return true;
	}
}
