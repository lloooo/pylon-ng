<?php
use pylon\impl\XAopRuleSet ;

/**
 * @ingroup aop
 * @brief  拦截器管理器
 */
class XAop
{

	static public $rules= null ;

	/**
	 * @brief  获得$pos 拦截点的拦截器规则集合
	 *
	 * @param $pos  拦截点
	 *
	 * @return
     * @remark
     * @code
     * 示例:
        XAop::append_by_match_name(".*", new AutoCommit());
    * @endcode
	 */
	static public function rule()
	{

		if(static::$rules == null )
        {
			static::$rules = new XAopRuleSet();
        }
        return  static::$rules ;
	}
    static function __callStatic($name,$arguments)
    {
        call_user_func_array( array(static::rule(),$name),$arguments );
    }
    static private function logger()
    {
        static $log_ins =null;
        if ($log_ins === null)
        {
            return XLogKit::logger("_pylon") ;
        }
        return $log_ins;
    }
	static public function using($conf)
	{
		return static::rule()->using($conf);
	}

	static public function using_all($pos)
	{
		return static::$rules->using_all();
	}

}


/**
 * @ingroup assembly
 * @brief  框架容器
 */
class XBox
{
    const ROUTER      = 'router'  ;
    const DAO              = 'dao'  ;
    const QUERY            = 'query' ;
    const SQLE             = 'SQLExecuter' ;
    const IDG              = 'IDGenterService' ;
    static $_objs    = array();
    static $_wheres  = array();
    static public function regist_where($call_level)
    {
        $bt     = debug_backtrace();
        $where  = '';
        if (count($bt) >=$call_level)
        {
            $frame  = $bt[$call_level];
            $where  = $frame['file']   . " : " . $frame['line'];
        }
        return $where ;
    }

    /**
     * @brief  替换原来的注册对象
     *
     * @param $key
     * @param $obj
     * @param $space  = '/'
     *
     * @return
     */
    static public function replace($key,$obj,$where,$space='/')
    {
        DBC::requireNotNull($obj,'$obj');
        $force = true ;
        static::registImpl($key,$obj,$space,$force,$where);
    }
    static public function regist($key,$obj,$where,$space='/')
    {
        DBC::requireNotNull($obj,'$obj');
        $force = false ;
        static::registImpl($key,$obj,$space,$force,$where);
    }
    /**
     * @brief  注册
     *
     * @param $key
     * @param $obj
     * @param $space  空间的意义在于，可以针对不同的实体对象，提供不同的数据库的访问器 ;
     * @param $force
     * @param $where
     *
     * @return
     */
    static private function registImpl($key,$obj,$space='/',$force=false,$where='')
    {
        DBC::requireNotNull($key,'$key');
        if (! isset(static::$_objs[$key]))
        {
            static::$_objs[$key] = array();
        }
        $space_obj = &static::$_objs[$key];
        if (! isset(static::$_wheres[$key]))
        {
            static::$_wheres[$key] = array();
        }
        $space_where = &static::$_wheres[$key];
        if($force === false)
        {
            if( isset($space_obj[$space]))
            {
                $first_where = $space_where[$space];
                throw new LogicException( "have regist $key obj in $space , first regist at [$first_where]");
            }
        }
        $space_obj[$space]      = $obj;
        $space_where[$space]    = $where;
    }
    static public function registByCLS($obj,$space='/',$force=false)
    {
        static::regist(get_class($obj),$obj,$space,$force);
    }

    static public function get($key,$space='/')
    {
        DBC::requireNotNull($key,'$key');

        while( true )
        {
            if (! isset(static::$_objs[$key]))
            {
                static::$_objs[$key] = array();
            }
            $space_obj = &static::$_objs[$key];
            if(isset($space_obj[$space]))
            {
                return $space_obj[$space];
            }
            else
            {
                if ($space === '/'  || $space == null || $space == ""  )
                    return null ;
                else
                    $space = dirname($space);

            }
        }
    }
    static public function space_objs($key)
    {
        if (! isset(static::$_objs[$key]))
        {
            static::$_objs[$key] = array();
        }
        $space_obj = &static::$_objs[$key];
        return $space_obj ;
    }
    static public function space_keys($key)
    {
        return array_keys(static::space_objs($key));
    }

    static public function must_get($key,$space='/')
    {
        $found = static::get($key,$space);
        if ($found === null)
        {
            throw new LogicException("unfound $key obj in $space");
        }
        return $found ;

    }

    static public function have($key,$space='/')
    {
        $found = static::get($key,$space);
        return $found != null ;
    }
    static public function clean($key=null)
    {
        if ( $key == null)
        {
            static::$_objs = array();
        }
        else if ( isset(static::$_objs[$key]))
        {
            static::$_objs[$key] = array();
        }
    }
}
