const app = getApp();
Page({
  data: {
    team_id: 0,
    token: '',
    userInfo: {},
    game: {},
    team: {},
    me: {},
    playID: -1,
    voice_dura: 0,
    user_id: 0,
    hasUserInfo: false,
    list: [],
    time: '',
    voice_url: '',
    canIUse: wx.canIUse('button.open-type.getUserInfo'),
    duration: 500,      // 切换时间 
    current:0,     // 切换指数
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
  alert1: function() {
    wx.showToast({
      title: '组建队伍成功才能领取哦',
      icon: "none",
      duration: 2000
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
          duration: 2000
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
    this.setData({
      team_id: option.team_id
    });
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
  },
  loaddatal:function(){
    wx.showLoading({
      title: '加载中•••'
    })
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
      app.globalData.dtimer = setInterval(function(){
        during = during - 1000;
        that.setData({
          time: that._transtime(during)
        });
      }, 1000);
    }
  },
  onUnload: function () {
    clearInterval(app.globalData.dtimer);
  },
  _transtime: function(i) {
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
  //语音播放
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
  toShare: function () {
    var team_id = this.data.team_id;
    wx.navigateTo({
      url: '/pages/index/share/share?team_id=' + team_id
    })
  },
})
