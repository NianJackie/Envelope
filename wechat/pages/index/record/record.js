const app = getApp();
Page({
  data: {
    team_id: 0,
    token: '',
    userInfo: {},
    game: {},
    quest: {},
    me: {},
    hints: ['按住输入语音', '按住输入语音', '正在录入语音...'],
    hint1: '按住输入语音',
    hint2: '按住输入语音',
    mide: false,          // 录音话筒控制 true为显示
    audiostate: false,         // 是否录入语音
    audio: {},       // 语音文件本地路径、时长ms
    time: 0,          // 语音时长
    playID: -1,
    user_id: 0,
    voice_dura: 0,
    hasUserInfo: false,
    list: [],
    voice_url: '',
    canIUse: wx.canIUse('button.open-type.getUserInfo'),
    duration: 500,      // 切换时间 
    current:0,     // 切换指数
  },
  onUnload: function () {
    clearInterval(app.globalData.dtimer);
  },
  alert1: function () {
    wx.showToast({
      title: '组建队伍成功才能领取哦',
      icon: "none",
      duration: 2000
    });
  },
  tolq: function (e) {
    var tok = app.globalData.token;
    var that = this;
    var postUrl = app.setConfig.url + '/index.php?g=Api&m=Game&a=receiveusermoney',
      postData = {
        team_id: this.data.team_id,
        token: tok
      };
    app.postLogin(postUrl, postData, function (res) {
      if (res.data.code == 20000) {
        wx.showToast({
          title: '领取成功,去我的记录中查看',
          icon: "none",
          duration: 2000
        });
        that.data.me.isreceiveusermoney = 1;
        that.setData({
          me: that.data.me
        });
      }
    });
  },
  receiveyuan: function (e) {
    var uid = e.currentTarget.dataset.uid;
    var tok = app.globalData.token;
    var that = this;
    if (this.data.user_id != uid) {
      wx.showToast({
        title: '只能领取自己的红包',
        icon: "none",
        duration: 2000
      });
      return;
    }
    var postUrl = app.setConfig.url + '/index.php?g=Api&m=Game&a=receive1yuan',
      postData = {
        team_id: this.data.team_id,
        token: tok
      };
    app.postLogin(postUrl, postData, function (res) {
      if (res.data.code == 20000) {
        wx.showToast({
          title: '领取成功',
          icon: "none",
          duration: 1200
        });
        that.data.list.map(i => {
          if (i.user_id == that.data.user_id) {
            i.receive1yuan = 1;
          }
        });
        that.setData({
          list: that.data.list
        });
      } else {
        wx.showToast({
          title: res.data.message,
          icon: "none",
          duration: 2000
        });
      }
    });
  },

  onLoad: function (option) {
    wx.showLoading({
      title: '加载中•••'
    });

    this.setData({
      team_id: option.team_id
    });

    this.loop();
  },

  loop:function(pid){
    if (!app.globalData.token){
      var that = this
      setTimeout(function () { that.loop(pid);},100)
    }else{
      var info = app.globalData.userInfo,
      tok = app.globalData.token;
      if (!info) {
        app.userInfoReadyCallback = res => {
          this.setData({
            userInfo: res.userInfo,
            token: tok,
            hasUserInfo: true
          })
        }
      } else {
        this.setData({
          userInfo: info,
          token: tok,
          hasUserInfo: true
        })
      }
      this.loaddatal();
    }
  },

  loaddatal:function(){
    var tok = this.data.token;
    var postUrl = app.setConfig.url + '/index.php?g=Api&m=Game&a=team',
        postData = {
          team_id: this.data.team_id,
          token: tok
        };
    app.postLogin(postUrl, postData, this.initial); 
  },

  initial: function (res) {
    if (res.data.code == 20000) {
      wx.hideLoading()
      this.setData({
        list: res.data.data,
        game: res.data.game,
        quest: res.data.quest,
        team: res.data.team,
        user_id: res.data.user_id,
        me: res.data.me
      })

      //倒计时
      var that = this;
      var now = new Date();
      if (res.data.game && res.data.game.nextgame && res.data.game.status == 1) {
        var during = res.data.game.nextgame.begin_time * 1000 - now.getTime();
      } else {
        var during = res.data.game.end_time * 1000 - now.getTime();
      }
      this.setData({
        item: this._transtime(during)
      });
      app.globalData.dtimer = setInterval(function () {
        during = during - 1000;
        that.setData({
          time: that._transtime(during)
        });
      }, 1000);
    }
  },

  _transtime: function (i) {
    var d = Math.floor(i / 1000 / 60 / 60 / 24);
    i = i - d * 1000 * 60 * 60 * 24;
    var h = Math.floor(i / 1000 / 60 / 60);
    i = i - h * 1000 * 60 * 60;
    var m = Math.floor(i / 1000 / 60);
    i = i - m * 1000 * 60;
    var s = Math.floor(i / 1000);
    if (d > 0) {
      return d + '天 ' + (h < 10 ? '0' + h : h) + ':' + (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
    } else {
      return (h < 10 ? '0' + h : h) + ':' + (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
    }
  },

  audioPlay: function (e) {
    var that = this,
      i = e.currentTarget.dataset.key;
    if (app.globalData.timer) { clearTimeout(app.globalData.timer); }
    //初始化播放器
    wx.pauseVoice();
    wx.stopVoice();
    if (i === that.data.playID) {
      that.setData({
        playID: -1
      })
    } else {
      var url = app.setConfig.url + '/' + that.data.list[i].voice_url;
      var millisecond = that.data.list[i].durat;
      //下载并播放语音
      wx.downloadFile({
        url: url,
        success: function (res) {
          console.log(url)
          that.setData({
            playID: i
          });
          wx.playVoice({
            filePath: res.tempFilePath
          })
          app.globalData.timer = setTimeout(function () {
            if (that.data.playID > -1) {
              that.setData({
                playID: -1
              })
            }
          }, millisecond);
        },
        fail: function (res) {
          console.log(res);
        }
      })
    }
  },

  toHome: function() {
    wx.reLaunch({
      url: '/pages/index/index'
    })
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
            }
          })
        } else {
          var hints = that.data.hints;
          wx.stopRecord();
          that.setData({
            mide: true,
            hint1: hints[2]
          })
          var sTime = (new Date()).getTime();
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
                  icon: "none",
                  mask: true,
                  duration: 2000
                })
                return false
              }
              
              wx.showLoading({
                title: '让口令飞一会儿',
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
                        qid: that.data.quest.id,
                        voice_url: JSON.parse(res.data).file_url,
                        durat: voice.millisecond,
                        token: that.data.token,
                        team_id: that.data.team_id
                      };
                      app.postLogin(postUrl, postData, function (res) {
                        if (res.data.code == 20000) {
                          wx.redirectTo({
                            url: '/pages/index/game/index?team_id=' + res.data.data.team_id
                          })
                        } else {
                          wx.showToast({
                            title: '再接再厉',
                            icon: "none",
                            duration: 2000
                          });
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

  toShare: function () {
    var team_id = this.data.team_id;
    wx.navigateTo({
      url: '/pages/index/share/share?team_id=' + team_id
    })
  },
})
