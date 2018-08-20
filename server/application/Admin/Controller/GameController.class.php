<?php
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class GameController extends AdminbaseController
{
	protected $model;

	public function _initialize() {
		parent::_initialize();
		$this->model = D("Game");
	}

  public function index()
  {
    $items = $this->model
        ->order("begin_time ASC")
        ->select();

    foreach ($items as &$v){
        $v['begin_time'] = date('Y-m-d H:i:s',$v['begin_time']);
        $v['end_time'] = date('Y-m-d H:i:s',$v['end_time']);
    }

    $this->assign("games", $items);
    $this->display();
  }

  public function show()
  {
    $id = $_GET['id'];
    $items = M('Quest')->where(['game_id' => $id])->select();
    
    $this->assign('items', $items);
    $this->assign('id', $id);
    $this->display();
  }

  public function quest_delete()
  {
    $id = $_GET['id'];
    $item = M('Quest')->where(['id' => $id])->delete();
    $this->success("删除口令成功",U("Game/index"));
  }

  public function game_add()
  {
    if (IS_GET) {
      $id = $_GET['id'];

      if ($id) {
        $item = M('Game')->where(['id' => $id])->find();
        $item['begin_time'] = date('Y-m-d H:i', $item['begin_time']);
        $item['end_time'] = date('Y-m-d H:i', $item['end_time']);
      }
  
      $this->assign('item', $item);
      $this->display();
    }
    
    if (IS_POST) {
      $_POST['begin_time'] = strtotime($_POST['begin_time']);
      $_POST['end_time'] = strtotime($_POST['end_time']);
      
      if (M('Game')->create()!==false) {
        if ($_POST['id']) {
          if (M('Game')->where(['id' => $_POST['id']])->save()!==false) {
            $this->success("保存赛期成功",U("Game/index"));
          } else {
            $this->error("保存赛期失败！");
          }
        } else {
          if (M('Game')->add()!==false) {
            $this->success("添加赛期成功",U("Game/index"));
          } else {
            $this->error("添加赛期失败！");
          }
        }
      } else {
        $this->error(M('Game')->getError());
      }
    }
  }

  public function over()
  {
    $id = $_GET['id'];
    $time = time();
    $game = M('Game')->where("status = %d and end_time < %d and id = %d", [0, $time, $id])->find();
    if ($game) {
      $teams = M('Team')->where(['status' => 1, 'game_id' => $game['id']])->order('score asc')->select();
      
      $index = 1;
      foreach ($teams as $team) {
        if ($index <= 10) {
          $money = $game['money'] * (11 - $index) / 10;
        } else {
          $money = 0;
        }
        M('Team')->where(['id' => $team['id']])->save([
          'status' => 2,
          'rank' => $index,
          'money' => $money
        ]);

        if ($money > 0) {
          $usermoney = sprintf("%.2f", $money / $game['number']);
          $users = M('QuestReceive')->where(['team_id' => $team['team_id']])->save(['amount' => $usermoney, 'isreceiveusermoney' => 0]);
        }
        $index++;
      }

      M('Game')->where(['id' => $game['id']])->save([
        'status' => 1
      ]);

      $this->success("结算成功",U("Game/index"));
    } else {
      $this->error("结算失败，未找到合法赛季",U("Game/index"));
    }
  }

  public function teams()
  {
    $id = $_GET['id'];
    $teams = M('Team')->where('game_id = '.$id)->order('status desc, score asc')->select();
    
    foreach ($teams as &$team) {
      if ($team['status'] == 1) {
        $res = M()->query("select * from (SELECT team_id, @curRank := @curRank + 1 AS rank FROM hb_team p, ( SELECT @curRank := 0 ) q where p.status = 1 and p.game_id = ".$id." ORDER BY score) as q where q.team_id=".$team['team_id']);
        $team['rank'] = $res[0]['rank'];
      }
    }

    // 如果当前赛季可以结算，那么计算结算金额
    $time = time();
    $game = M('Game')->where("status = %d and end_time < %d and id = %d", [0, $time, $id])->find();
    if ($game) {
      $game['money_all'] = 0;
      foreach ($teams as &$team) {
        if ($team['status'] == 1) {
          $team['ydmoney'] = $game['money'] * (11 - $team['rank']) / 10;
          $game['money_all'] += $team['ydmoney'];
        }
      }
    }

    $this->assign('game', $game);
    $this->assign('id', $id);
    $this->assign('teams', $teams);
    $this->display();
  }

  public function quest_add()
  {
    if (IS_GET) {
      $id = $_GET['id'];

      if ($id) {
        $item = M('Quest')->where(['id' => $id])->find();
      }
  
      $this->assign('item', $item);
      $this->display();
    }
    
    if (IS_POST) {
      $_POST['user_id'] = sp_get_current_admin_id();
      $_POST['add_time'] = time();
      if (M('Quest')->create()!==false) {
        if ($_POST['id']) {
          if (M('Quest')->where(['id' => $_POST['id']])->save()!==false) {
            $this->success("保存口令成功",U("Game/index"));
          } else {
            $this->error("保存口令失败！");
          }
        } else {
          if (M('Quest')->add()!==false) {
            $this->success("添加口令成功",U("Game/index"));
          } else {
            $this->error("添加口令失败！");
          }
        }
      } else {
        $this->error(M('Quest')->getError());
      }
    }
  }
}
