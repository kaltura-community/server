<?php

class kKavaCustomReports extends kKavaReportsMgr
{

	protected static $custom_reports = null;

	protected static function initMap()
	{
		if (is_null(self::$custom_reports))
		{
			self::$custom_reports = kConf::getMap('custom_reports');
		}
	}

	public static function getReportDef($report_type)
	{
		self::initMap();
		return isset(self::$custom_reports[-$report_type]) ? self::$custom_reports[-$report_type] : null;
	}

}
