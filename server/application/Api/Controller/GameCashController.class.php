<?php
namespace Api\Controller;
use Think\Controller;
use Common\Controller\WeixinController;
use Common\Lib\Queue;

class GameCashController extends Controller{
  
  public function result() {
    // $time = time();
    // $game = M('Game')->where("status = %d and end_time < %d", [0, $time])->find();
    // if ($game) {
    //   $teams = M('Team')->where(['status' => 1, 'game_id' => $game['id']])->order('score asc')->select();
      
    //   $index = 1;
    //   foreach ($teams as $team) {
    //     if ($index <= 10) {
    //       $money = $game['money'] * (11 - $index) / 10;
    //     } else {
    //       $money = 0;
    //     }
    //     M('Team')->where(['id' => $team['id']])->save([
    //       'status' => 2,
    //       'rank' => $index,
    //       'money' => $money
    //     ]);

    //     if ($money > 0) {
    //       $usermoney = sprintf("%.2f", $money / $game['number']);
    //       $users = M('QuestReceive')->where(['team_id' => $team['team_id']])->save(['amount' => $usermoney, 'isreceiveusermoney' => 0]);
    //     }
    //     $index++;
    //   }

    //   M('Game')->where(['id' => $game['id']])->save([
    //     'status' => 1
    //   ]);

    //   $this->ajaxReturn(['code'=>20000, 'msg'=>'已经结算']);
    // } else {
    //   $this->ajaxReturn(['code'=>54000, 'msg'=>'无可结算的赛季']);
    // }
  }

}