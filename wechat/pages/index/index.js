
  //index.js
//获取应用实例
const app = getApp()

Page({
  data: {
    userInfo: {},   // 用户信息
    hasUserInfo: false,  // 用户授权
    game: null,
    canIUse: wx.canIUse('button.open-type.getUserInfo'),   //  检测小程序版本兼容
    enveindex: 0,     //  模式控制参数
    hints: ['按住输入语音', '按住输入语音', '正在录入语音...'],
    hint1: '按住输入语音',
    hint2: '按住输入语音',
    pid: 0,
    textCN:'',     // 口令输入框内容
    mide: false,          // 录音话筒控制 true为显示
    btn:'生成语音口令',      // 支付按钮提示语
    audiostate: false,         // 是否录入语音
    audio: {},       // 语音文件本地路径、时长ms
    time: 0,          // 语音时长
    playID: -1,        // 语音播放器控制id
    control: true,    // 提交按钮控制器
    duration:500
  },
  //事件处理函数
  //词条切换
  bindchange:function(e){
    var ii = parseFloat(e.detail.current);
    var pid = this.data.game.quests[ii].id;
    this.setData({
      enveindex: ii,
      pid: pid
    })  
  },
  onLoad () {
    // wx.navigateTo({
    //   url: '/pages/index/record/record?team_id=' + 1518083962
    // });
  },
  
  //长按录音
  longtap: function (e) {
    var that = this;
    //获取用户录音授权
    wx.getSetting({
      success: res => {
        if (!res.authSetting['scope.record']) {
          //未授权获取录音授权
          wx.authorize({
            scope: 'scope.record',
            success() {
              // 用户已经同意小程序使用录音功能，后续调用 wx.startRecord 接口不会弹窗询问
            }
          })
        } else {
          //已同意授权录音
          var hints = that.data.hints;
          wx.stopRecord();
          that.setData({
            mide: true,
            hint1: hints[2]
          })
          var sTime = (new Date()).getTime();
          //开始录音
          wx.startRecord({
            success: function (res) {
              var eTime = (new Date()).getTime();
              var duration = (eTime - sTime);
              duration > 60000 ? duration = 60000 : false;
              that.setData({
                mide: false,
                hint1: hints[0]
              })
              if (duration < 1000) {
                wx.showToast({
                  title: '录音时间太短',
                  mask: true,
                  icon: "none",
                  duration: 2000
                })
                return false
              }
              
              wx.showLoading({
                title: '让口令飞一会儿',
                icon: "none",
                mask: true
              })
              var tempFilePath = res.tempFilePath,
                voice = {},
                voices = that.data.ls;

              //保存文件到本地
              wx.saveFile({
                tempFilePath: tempFilePath,
                success: function (res) {
                  var savedFilePath = res.savedFilePath;
                  var name = that.data.userInfo.nickName,
                    imgurl = that.data.userInfo.avatarUrl,
                    src = savedFilePath,
                    width = '',
                    lsje = '';
                  const formatNumber = n => {
                    n = n.toString()
                    return n[1] ? n : '0' + n
                  }
                  var date = new Date(),
                    month = [date.getMonth() + 1].map(formatNumber),
                    day = [date.getDate()].map(formatNumber),
                    hour = [date.getHours()].map(formatNumber),
                    minute = [date.getMinutes()].map(formatNumber);
                  date = month + '月' + day + '日 ' + hour + ':' + minute;
                  width = (45 + 55 * duration / 1000 / 30) + '%';

                  voice = {
                    imgurl: imgurl,
                    name: name,
                    src: src,
                    width: width,
                    duration: Math.round(duration / 1000),
                    millisecond: duration,
                    time: date,
                    lsje: lsje,
                    local: true
                  };

                  const uploadTask = wx.uploadFile({
                    url: app.setConfig.url + '/index.php/Asset/Upload/plupload',
                    filePath: savedFilePath,
                    name: 'file',
                    formData: {
                      'token': that.data.token,
                    },
                    success: function (res) {
                      var zlq = parseFloat(that.data.zlq) + 1;
                      var postUrl = app.setConfig.url + '/index.php?g=Api&m=Game&a=saveEnveReceive';
                      var postData = {
                        qid: that.data.pid,
                        voice_url: JSON.parse(res.data).file_url,
                        durat: voice.millisecond,
                        token: that.data.token
                      };
                      app.postLogin(postUrl, postData, function (res) {
                        if (res.data.code == 20000) {
                          wx.navigateTo({
                            url: 'game/index?team_id=' + res.data.data.team_id
                          })
                        } else {
                          wx.showToast({
                            title: '再接再厉',
                            icon: "none",
                            duration: 2000
                          })
                        }
                      });
                    }
                  })
                  uploadTask.onProgressUpdate((res) => {
                  })
                }
              })
            },
            fail: function (res) {
              //录音失败
              wx.showToast({
                title: '录音失败',
                mask: true,
                icon: "none",
                duration: 2000
              })
              that.setData({
                mide: false,
                hint1: hints[0]
              })
            }
          })
        }
      }
    })

  },

  //录音被中断
  touchcancel: function (e) {
    wx.stopRecord();
    var hints = this.data.hints;
    this.setData({
      mide: false,
      hint1: hints[0]
    })
  },

  //录音结束
  touchend: function (e) {
    var that = this,
      hints = this.data.hints;
    setTimeout(function () {
      that.setData({
        mide: false,
        hint1: hints[0]
      })
      wx.stopRecord();
    }, 300)
  },
  
  // 转发
  onShareAppMessage: function (res) {
    return {
      title: '邀您参加“嘴”强王者瓜分现金大奖',
      path: '/pages/index/index',
      success: function (res) {
        // 转发成功
        wx.showToast({
          title: '转发成功',
          icon: "none",
          duration: 2000
        })
      },
      fail: function (res) {
        // 转发失败
      }
    }
  },

  //获取登录信息
  onShow:function(){
    wx.showLoading({
      title: '加载中•••',
      mask: true
    })
    this.loop();
  },
  
  loop: function () {
    var info = app.globalData.userInfo,
        tok = app.globalData.token;
    if (info && !this.data.hasUserInfo){
      wx.showLoading({
        title: '数据初始化',
        mask: true
      })
      
      this.setData({
        userInfo: info,
        hasUserInfo: true
      })
    }
    if (!tok) {
      var that = this
      setTimeout(function () { that.loop(); }, 100)
    } else {
      this.setData({
        token: tok
      })
      var postUrl = app.setConfig.url + '/index.php?g=User&m=Consumer&a=userInfo',
        postData = {
          token: tok
        };
      app.postLogin(postUrl, postData, this.initial);
    }
  },
  
  initial: function (res) {
    var that = this;
    if (res.data.code == 20000) {
      var data = res.data.data;
      var initials = {
        yjpt: data.commision,      
        yjgg: data.commision_adv,      
        minfc: data.amount_min,        
        minlq: data.receive_amount_min    
      };

      var postUrl = app.setConfig.url + '/index.php?g=Api&m=Game&a=index',
      postData = {
        token: app.globalData.token
      };
      app.postLogin(postUrl, postData, function(res){
        if (res.data.code == 20000) {
          that.setData({
            game: res.data.data,
            pid: res.data.data.quests.length ? 
              res.data.data.quests[0].id : 0
          })
        } else {
          that.setData({
            game: 0
          })
          wx.showLoading({
            title: res.data.msg,
            mask: true,
            duration: 1500
          })
        }
      });

      wx.hideLoading()
      this.setData({
        balance: data.amount,
        initials: initials,
        imgsTP: data.adv_list
      })
    }
  }
})
