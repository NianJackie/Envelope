<?php
/**
 * 比赛期数类
 * author universe.h
 */
namespace Api\Controller;
use Think\Controller;
use Common\Controller\InterceptController;
use Common\Controller\WeixinController;
use Common\Controller\AdvController;
use Common\Lib\Baidu\Sample;
use Common\Lib\Pinyin;
use Common\Lib\Wxpay\Wxpay;
use Common\Lib\Queue;
use Admin\Controller\PublicController;

class GameController extends InterceptController {

  // 获取当前赛季的内容
  public function index() {
    $game_model = M("Game");
    $time = time();
    $field = 'id,title,number,FROM_UNIXTIME(begin_time,\'%m月%d日 %H:%i\') begin_time,FROM_UNIXTIME(end_time,\'%m月%d日 %H:%i\') end_time,status';
    $info = $game_model->field($field)->where("status=%d and begin_time < %d and end_time > %d",array(0, $time, $time))->order('id desc')->find();
    if(!$info){
      // 如果当前赛季没有在，那显示上一个赛季的
      // $info = $game_model->field($field)->where("status = %d",array(1))->order('end_time desc')->find();
      if (!$info) {
        $this->ajaxReturn(['code'=>20400, 'msg'=>'没有更多了']);
      }
    }
    
    // 赛季红包列表
    $quest_model = M("Quest");
    $field = 'id,quest,num,receive_num,FROM_UNIXTIME(add_time,\'%m月%d日 %H:%i\') add_time';
    $info['quests'] = $quest_model->field($field)->where("del=0 and game_id = %d",array($info['id']))->order('id desc')->select();
    
    $this->ajaxReturn(['code'=>20000, 'msg'=>'success', 'data'=>$info]);
  }

  public function rank() {
    $game_model = M("Game");
    $time = time();
    $field = 'id,title,number,FROM_UNIXTIME(begin_time,\'%m月%d日 %H:%i\') begin_time,FROM_UNIXTIME(end_time,\'%m月%d日 %H:%i\') end_time,status';
    $info = $game_model->field($field)->where("status=%d and begin_time < %d and end_time > %d",array(0, $time, $time))->order('id desc')->find();
    if(!$info){
      $info = $game_model->field($field)->where("status = %d",array(1))->order('end_time asc')->find();
      if (!$info) {
        $this->ajaxReturn(['code'=>20400, 'msg'=>'没有更多了']);
      }
    }
    
    $res['game'] = $info;
    $res['teams'] = M('Team')->where([
      'game_id' => $info['id'], 
      'status' => ['exp', 'in (1, 2)']
    ])->order('score asc')->select();

    foreach ($res['teams'] as &$r) {
      $r['users'] = M('QuestReceive')->where(['team_id' => $r['team_id']])->select();
    }
    
    $this->ajaxReturn(['code'=>20000, 'msg'=>'success', 'data'=>$res]); 
  }

  public function teams() {
    $user_id = $this->user_id;
    $teamids = M()->query("select DISTINCT team_id from hb_quest_receive where user_id = $user_id;");
    if (!$teamids) {
      $this->ajaxReturn(['code'=>50000, 'msg'=>'没有更多了']); 
    }
    $tmp = [];
    foreach ($teamids as $tids) {
      $tmp[] = $tids['team_id'];
    }
    $tmp = implode(',', $tmp);

    $teams = M('Team')->where("team_id in ($tmp)")->select();

    foreach ($teams as &$team) {
      if ($team['status'] == 1) {
        $res = M()->query("select * from (SELECT team_id, @curRank := @curRank + 1 AS rank FROM hb_team p, ( SELECT @curRank := 0 ) q where p.status = 1 and p.game_id = ".$team['game_id']." ORDER BY score) as q where q.team_id=".$team['team_id']);
        $team['rank'] = $res[0]['rank'];
      }
      $i = M('QuestReceive')->where(['user_id' => $user_id, 'team_id' => $team['team_id']])->find();
      if ($i['isreceiveusermoney'] == 1) {
        $team['usermoney'] = $i['amount'];
      } else {
        $team['usermoney'] = 0;
      }
    }

    $this->ajaxReturn(['code'=>20000, 'msg'=>'success', 'data'=>$teams]); 
  }

  public function receiveusermoney() {
    $team_id = I('post.team_id/d');
    $model = M('QuestReceive');
    $info = $model->where(['team_id' => $team_id, 'user_id' => $this->user_id, 'isreceiveusermoney' => 0])->find();
    if (!$info) {
      return $this->ajaxReturn(['code'=>54000, 'msg'=>'未找到']); 
    }
    
    M('WxUser')->where(['id' => $this->user_id])->setInc('amount', $info['amount']);
    $model->where(['id' => $info['id']])->save([
      'isreceiveusermoney' => 1
    ]);

    $this->ajaxReturn(['code'=>20000, 'msg'=>'success', 'data'=>$teams]); 
  }

  public function team() {
    $team_id = I('post.team_id/d');
    $model = M('QuestReceive');
    $info = $model->where(['team_id' => $team_id])->select();

    $game = M('Game')->where(['id' => $info[0]['game_id']])->find();
    $game['snum'] = $game['number'] - count($info);
    $game['time'] = $model->where(['team_id' => $team_id])->sum('durat');
    $game['time'] = (int)$game['time'] / 1000;
    if ($game['status'] == 1) {
      $game['nextgame'] = M('Game')->where('status = 0 and begin_time > '.time())->order('begin_time asc')->find();
    }

    $game['creator'] = $model->where(['iscreator' => 1, 'team_id' => $team_id])->find();
    $num = $game['number'];
    $gameid = $game['id'];

    $rows = M()->query("select * from (select team_id, sum(durat) as score, count(*) as cc from hb_quest_receive where game_id = $gameid group by team_id) as b where b.cc = $num order by score asc;");
    $index = 1;
    foreach ($rows as $row) {
      if ($row['team_id'] == $team_id) {
        $game['rank'] = $index;
        break;
      }
      $index++;
    }

    $quest = M('Quest')->where(['id' => $info[0]['qid']])->find();

    $team = M('Team')->where(['team_id' => $team_id])->find();
    if ($team['status'] == 2) {
      $team['usermoney'] = sprintf("%.2f", $team['money'] / $team['number']);
      
    }
    $team['inteam'] = M('QuestReceive')->where(['team_id' => $team_id, 'user_id' => $this->user_id])->count();

    $me = [];
    foreach ($info as &$i) {
      if ($i['user_id'] == $this->user_id) {
        $me = $i;
      }
      $i['receive1yuan'] = M('QuestReceive')->where(['isreceive' => 1, 'user_id' => $i['user_id']])->count();
    }

    $this->ajaxReturn(['code'=>20000, 'msg'=>'suceess', 'data'=>$info, 'game'=>$game, 'quest'=>$quest, 'team'=>$team, 'user_id'=>$this->user_id, 'me'=>$me]);
  }

  public function receive1yuan() {
    $count = M('QuestReceive')->where(['isreceive' => 1, 'user_id' => $this->user_id])->count();
    if ($count) {
      $this->ajaxReturn(['code'=>50000, 'msg'=>'已领取参与奖']); 
    }
    $count = M('QuestReceive')->where(['isreceive' => 0, 'user_id' => $this->user_id])->count();
    if (!$count) {
      $this->ajaxReturn(['code'=>50000, 'msg'=>'无可领取的']); 
    }
    
    $t = M('QuestReceive')->where(['user_id' => $this->user_id])->order('id desc')->find();
    $qr = M('QuestReceive')->where(['id' => $t['id']])->save([
      'isreceive' => 1
    ]);

    $ret = M('WxUser')->where(['id' => $this->user_id])->setInc('amount', 1);

    $this->ajaxReturn(['code'=>20000, 'msg'=>'领取成功']); 
  }

  public function saveEnveReceive() {
    $post_data = I('post.');
    $qid = I('post.qid/d');

    $enve_model = M("Quest");
  
    $home = "/www/wwwroot/longtou.upcircle.cn";
    $zm_path = substr($post_data['voice_url'], 0, 30);
    $filename = trim(substr($post_data['voice_url'], 30),'.silk');
   
    $info = $enve_model->where("id='%d' and del=0", [$qid])->find();
    if (!$info) {
        $this->ajaxReturn(['code'=>40500, 'msg'=>'不是有效问题!']);
    }

    $filepath = $post_data['voice_url'];
    
    $sample = new Sample();
    $post_data['voice_url'] = $home.$post_data['voice_url'];
    // $post_data['voice_url'] = $home.'/data/upload/default/20180123/5a673d762bdb7.silk';
  
    $aa = $resText = $sample->identify($post_data);
    $resText = json_decode($resText,true);
    $sampleText = str_replace('，', '', $resText['result'][0]);

    $post_data['voice_url'] = $filepath;

    file_put_contents("/www/wwwroot/longtou.upcircle.cn/application/Api/Controller/sample.txt",serialize($resText)."\n",FILE_APPEND);

    $py = new Pinyin();
    $samplepy = $py->getPY($sampleText);

    file_put_contents("/www/wwwroot/longtou.upcircle.cn/application/Api/Controller/sample.txt",$samplepy."\n",FILE_APPEND);
    //删除没有说话后的文件
    if(!$samplepy){
        $pcm_file ="{$home}{$zm_path}{$filename}.pcm";
        $mp3_file ="{$home}{$zm_path}{$filename}.mp3";
        $silk_file ="{$home}{$zm_path}{$filename}.silk";
        file_put_contents("/www/wwwroot/longtou.upcircle.cn/application/Api/Controller/删除pcm记录.txt",serialize($pcm_file));
        file_put_contents("/www/wwwroot/longtou.upcircle.cn/application/Api/Controller/删除MP3记录.txt",serialize($mp3_file));
        $cmd = "rm -f  $pcm_file" ; 
        $res = shell_exec($cmd);
        $cmd = "rm -f  $mp3_file" ; 
        $res = shell_exec($cmd);
        $cmd = "rm -f  $silk_file" ; 
        $res = shell_exec($cmd);
    
        $this->ajaxReturn(['code'=>40000, 'msg'=>'啊，识别失败了，请重试',$resText=>$sampleText,$samplepy=>$aa,'11'=>$aa]);
    };
    $compareStr = '';
  
    //删除识别后的文件
    $pcm_file ="{$home}{$zm_path}{$filename}.pcm";
    $mp3_file ="{$home}{$zm_path}{$filename}.mp3";
    file_put_contents("/www/wwwroot/longtou.upcircle.cn/application/Api/Controller/删除pcm记录.txt",serialize($pcm_file));
    file_put_contents("/www/wwwroot/longtou.upcircle.cn/application/Api/Controller/删除MP3记录.txt",serialize($mp3_file));
    $cmd = "rm -f  $pcm_file" ; 
    $res = shell_exec($cmd);
    $cmd = "rm -f  $mp3_file" ; 
    $res = shell_exec($cmd);

    $compareStr = $info['quest_py'];

    if($samplepy != $compareStr){
        $is_pass = false;

        $str = similar_text($samplepy, $compareStr);
        $quest_len = strlen($compareStr);

        file_put_contents("/www/wwwroot/longtou.upcircle.cn/application/Api/Controller/sample.txt",$str / $quest_len."\n",FILE_APPEND);

        //小于两个字
        if(($quest_len < 4 && $samplepy == $compareStr)
            || ($quest_len < 16 &&  $str / $quest_len >= 0.9)
            || ($quest_len < 28 && $str / $quest_len >= 0.95)
            || ($quest_len < 40 && $str / $quest_len >= 0.92)
            || ($str / $quest_len >= 0.8)
        ){
            $is_pass = true;
        }

        // $is_pass = true;

        if ($is_pass == false){
            $pcm_file ="{$home}{$zm_path}{$filename}.pcm";
            $mp3_file ="{$home}{$zm_path}{$filename}.mp3";
            $silk_file ="{$home}{$zm_path}{$filename}.silk";
            file_put_contents("/www/wwwroot/longtou.upcircle.cn/application/Api/Controller/删除pcm记录.txt",serialize($pcm_file));
            file_put_contents("/www/wwwroot/longtou.upcircle.cn/application/Api/Controller/删除MP3记录.txt",serialize($mp3_file));
            $cmd = "rm -f  $pcm_file" ; 
            $res = shell_exec($cmd);
            $cmd = "rm -f  $mp3_file" ; 
            $res = shell_exec($cmd);
            $cmd = "rm -f  $silk_file" ; 
            $res = shell_exec($cmd);
        
            $this->ajaxReturn(['code'=>40000, 'msg'=>'啊，识别失败了，请重试',$aa=>$samplepy,$str=>$info['quest_py']]);
        }
    }
    
    M()->startTrans();
    $enve_receive_model = M('QuestReceive');
  
    $team = M('Team')->create();

    //如果没有team_id生成team_id
    if (!$post_data['team_id']) {
        $post_data['team_id'] = time();
        $post_data['iscreator'] = 1;

        $team['creator'] = $this->user_id;
        $team['add_time'] = time();
        $team['team_id'] = $post_data['team_id'];
        $team['score'] = $post_data['durat'];
        $team['name'] = $this->__get('nick_name');
        $team['game_id'] = $info['game_id'];
        $team['qid'] = $post_data['qid'];
        $team['number'] = 1;
        $team_id = M('Team')->add($team);
    } else {
    //如果有team_id，那么检查是否已经参与过本次team，并校验队伍成员数量
        $count = $enve_receive_model->where(['team_id' => $post_data['team_id']])->count();

        $game = M('Game')->where(['id' => $info['game_id']])->find();
        if ($game['number'] <= $count){
          $this->ajaxReturn(['code'=>50001, 'msg'=>'本队伍成员已满']);
        }

        $count = $enve_receive_model->where(['team_id' => $post_data['team_id'], 'user_id' => $this->user_id])->count();      
        if ($count) {
          $this->ajaxReturn(['code'=>50002, 'msg'=>'您已经参与过了，请不要重复参与']);
        }
        
        $team = M('Team')->where(['team_id' => $post_data['team_id']])->find();
        $team['number'] = $team['number'] + 1;
        $team['score'] += $post_data['durat'];
        if ($team['number'] == $game['number']) {
          $team['status'] = 1;
        }
        M('Team')->where(['team_id' => $post_data['team_id']])->save([
          'number' => $team['number'],
          'score' => $team['score'],
          'status' => $team['status']
        ]);
    }
    
    $post_data['receive_answer'] = $samplepy;
    $post_data['user_id'] = $this->user_id;
    $post_data['nick_name'] = $this->__get('nick_name');
    $post_data['head_img'] = $this->__get('head_img');
    $post_data['add_time'] = time();
    $post_data['game_id'] = $info['game_id'];
    $post_data['sex'] = $this->__get('sex');

    //如果第一次参与，给1元
    $count = $enve_receive_model->where([
      'user_id' => $this->user_id
    ])->count();
    if ($count == 0) {
      $post_data['receive_amount'] = 1;
    }
    
    $last_id = M('QuestReceive')->add($post_data);
    if(!$last_id){
      $msg = $enve_receive_model->getError() ?: '系统繁忙';
      $this->ajaxReturn(['code' => 50000, 'msg' => $msg ]);
    };
    
    M()->commit();

    $this->ajaxReturn(['code'=>20000, 'msg'=>'success', 'data'=> $post_data ]);
  }

  public function get_code() {
		$data['path'] = I('post.page/s');
		$data['width'] = I('post.width/d',430);
		$data['auto_color'] = I('post.auto_color/s');
		$data['line_color'] = I('post.line_color/s');
		$team_id = I('post.team_id/d');
		$data['scene'] = $team_id;
		
		if(!$data['path']){
			$this->ajaxReturn(['code'=>40000, 'msg'=>'跳转链接不能为空']);
		}
		
		$share_pic = M('share_pic')->where(array('team_id'=>$team_id))->find();
		if (!empty($share_pic)) {
			$this->ajaxReturn(['code'=>20000, 'msg'=>'success', 'data'=>$share_pic['purl']],false,JSON_UNESCAPED_SLASHES);
		}
    
		$info = WeixinController::instance()->get_wxa_code($data);
		$path = C('UPLOADPATH').'code/';
		if(!is_dir($path)){
			mkdir(iconv("UTF-8", "GBK", $path),0777,true);
		}
		$file = rand(10000000,99999999).'.png';
		$paths = $path . $file;
		$res = file_put_contents( $paths,$info );
		
		$path_head = $path.'head'.$file;
		
		if($res){
			$res = file_put_contents( $path_head, file_get_contents($this->head_img));
			
			$imgs = array(
					'dst' => 'data/upload/back.png',
					'pic' => 'data/upload/redtips.png',
					'src' => $paths,
					'head' => $path_head,
			);
			
			if($res){
				$this->tosize($imgs['head'],96,true);
				$roundImg = $this->toround($imgs);
				$this->mergerImg($imgs,$roundImg);
				$con=[
						'tit'=> I('post.tit/s','发起了一个红包游戏'),
						'con'=> '邀您参加"嘴"强王者瓜分现金大奖',
        ];
				$this->totxt($imgs,$con);
				
				M('share_pic')->add(array('team_id'=>$team_id, 'purl'=>$paths, 'createtime'=>time()));
				$this->ajaxReturn(['code'=>20000, 'msg'=>'success', 'data'=>$paths],false,JSON_UNESCAPED_SLASHES);
			}
			
		}
		$this->ajaxReturn(['code'=>40000, 'msg'=>'生成失败']);
	}
	
	//改变图片大小
	public function tosize($url,$max = 200,$is_pic = false){
		//因为PHP只能对资源进行操作，所以要对需要进行缩放的图片进行拷贝，创建为新的资源
		$src=imagecreatefromjpeg($url);
		
		//取得源图片的宽度和高度
		$size_src=getimagesize($url);
		$w=$size_src['0'];
		$h=$size_src['1'];
		if($max >= $w){
			return false;
		}
		
		//根据最大值为300，算出另一个边的长度，得到缩放后的图片宽度和高度
		if($w > $h){
			$w=$max;
			$h=$h*($max/$size_src['0']);
		}else{
			$h=$max;
			$w=$w*($max/$size_src['1']);
		}
		//声明一个$w宽，$h高的真彩图片资源
		$image=imagecreatetruecolor($w, $h);
		
		//关键函数，参数（目标资源，源，目标资源的开始坐标x,y, 源资源的开始坐标x,y,目标资源的宽高w,h,源资源的宽高w,h）
		imagecopyresampled($image, $src, 0, 0, 0, 0, $w, $h, $size_src['0'], $size_src['1']);
		
		if(!$is_pic){
			return $image;
		}
		
		//告诉浏览器以图片形式解析
		header('content-type:image/png');
		
		imagepng($image,$url);
		
		//销毁资源
		imagedestroy($image);
	}
	
	//合并
	public function mergerImg($imgs,$oth) {
		//生成原型图
		imagepng($oth, $imgs['src']);
		list($max_width, $max_height) = getimagesize($imgs['dst']);
		$dests = imagecreatetruecolor($max_width, $max_height);
		
		$dst_im = imagecreatefrompng($imgs['dst']);
		imagecopy($dests,$dst_im,0,0,0,0,$max_width,$max_height);
		imagedestroy($dst_im);
		
		//合成二维码
		$src_im = imagecreatefrompng($imgs['src']);
		imagealphablending($src_im,true);
		$src_info = getimagesize($imgs['src']);
		imagecopy($dests, $src_im,$max_width/2-90,$max_height/2-90,0,0,$src_info[0],$src_info[1]);
		
		//合成头像
		$head_img = imagecreatefrompng($imgs['head']);
		//获取头像长宽等信息
		$head_info = getimagesize($imgs['head']);
		
		imagecopy($dests, $head_img,$max_width/2-$head_info[0]/2,30,0,0, 96,96);
		imagedestroy($src_im);
		
		header("Content-type: image/png");
		imagepng($dests,$imgs['src']);
		//        imagepng($dests);
		unlink($imgs['head']);
	}
	
	//添加文字
	public function totxt($src,$textArr){
		//获取图片信息
		$info = getimagesize($src['src']);
		//        var_dump($info);die;
		//获取图片扩展名
		$type = image_type_to_extension($info[2],false);
		//动态的把图片导入内存中
		$fun = "imagecreatefrom{$type}";
		$image = $fun($src['src']);
		//指定字体颜色
		$col = imagecolorallocatealpha($image,255,255,255,1);
		$font_file = 'simplewind/Core/Library/Think/Verify/zhttfs/1.ttf';
		
		$b = imagettfbbox(20,0, $font_file,$textArr['tit'] );
		
		$textX=ceil(($info[0] - $b[2]) / 2);
		$lengb = abs(b[0] - $b[2]);
		//指定字体内容
		imagefttext($image, 20, 0,  $textX-16, $info[1]/5, $col, $font_file,mb_convert_encoding($textArr['tit'],'html-entities','UTF-8'));
    
		$b = imagettfbbox(28,0, $font_file,$textArr['con'] );
		//指定字体内容
		imagefttext($image, 28, 0,  ceil(($info[0] - $b[2]) / 2), $info[1]/3.8, $col, $font_file,mb_convert_encoding($textArr['con'],'html-entities','UTF-8'));
		
		
		//合成头像
		$pic = imagecreatefrompng($src['pic']);
		
		//获取头像长宽等信息
		$head_info = getimagesize($src['pic']);
		imagecopy($image, $pic, $textX +$lengb-8,$info[1]/5.7,0,0, 30,30);
		
		//指定输入类型
		header('Content-type:'.$info['mime']);
		//动态的输出图片到浏览器中
		$func = "image{$type}";
		$func($image,$src['src']);
		//销毁图片
		imagedestroy($image);
		
	}
	
	//生成圆二维码
	public function toround($imgs,$path='./'){
		//       $w = 100;  $h=100; // original size
		$sizeImg = $this->tosize($imgs['src'], 180);
		header('content-type:image/png');
		imagepng($sizeImg,$imgs['src']);
		//       $dest_path = $path.uniqid().'.png';
		$src = imagecreatefromstring(file_get_contents($imgs['src']));
		//取得源图片的宽度和高度
		list($w,$h)=getimagesize($imgs['src']);
		
		$newpic = imagecreatetruecolor($w,$h);
		imagealphablending($newpic,false);
		$transparent = imagecolorallocatealpha($newpic, 0, 0, 0, 127);
		
		imageantialias ( $transparent ,true );
		$r=$w/2;
		for($x=0;$x<$w;$x++)
			for($y=0;$y<$h;$y++){
				$c = imagecolorat($src,$x,$y);
				$_x = $x - $w/2;
				$_y = $y - $h/2;
				if((($_x*$_x) + ($_y*$_y)) < ($r*$r)){
					imagesetpixel($newpic,$x,$y,$c);
				}else{
					imagesetpixel($newpic,$x,$y,$transparent);
				}
		}
		
		imagesavealpha($newpic, true);
		
		//       header('content-type:image/png');
		//        imagepng($newpic);
		//        imagepng($newpic, $dest_path);
		imagedestroy($src);
		// unlink($url);
		return $newpic;
	}
}