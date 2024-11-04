<?php

namespace app\api\controller;

use app\api\QfShop;
use quarkPlugin\QuarkPluginPlus;
use Lizhichao\Word\VicWord;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;


class SourcePlus extends QfShop
{
    /**
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws DataNotFoundException
     */
    public function getALLQuarkShareLink()
    {
        ignore_user_abort(true);
        $quarkPluginPlus = new QuarkPluginPlus();
        return jok("Hello World!",$quarkPluginPlus-> getAllShareLink());
    }


}
