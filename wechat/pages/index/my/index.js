const app = getApp();
Page({
  data: {
    team_id: 0,
    token: '',
    userInfo: {},
    game: {},
    amount: 0,
    hasUserInfo: false,
    list: [],
    canIUse: wx.canIUse('button.open-type.getUserInfo'),
  },
  
  onShow: function (option) {
    var info = app.globalData.userInfo,
        tok = app.globalData.token;
    console.log(info);
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
    var that = this;
    var postUrl = app.setConfig.url + '/index.php?g=Api&m=Game&a=teams',
        postData = {
          token: tok
        };
    app.postLogin(postUrl, postData, this.initial);
    
    var postUrl = app.setConfig.url + '/index.php?g=User&m=Consumer&a=userInfo',
        postData = {
          token: tok
        };
    app.postLogin(postUrl, postData, function(res) {
      that.setData({
        amount: res.data.data.amount
      })
    });
  },
  // 发出的
  initial: function (res) {
    if (res.data.code == 20000) {
      wx.hideLoading()
      res.data.data.map(i => {
        i.money = (i.money / i.number).toFixed(2)
        i.score = (i.score / 1000).toFixed(2)
      })
      this.setData({
        list: res.data.data
      })
    }
  },
  toTeam: function (e) {
    wx.navigateTo({
      url: '/pages/index/game/index?team_id=' + e.currentTarget.dataset.id
    })
  },
  toCharge: function () {
    var team_id = this.data.team_id;
    wx.navigateTo({
      url: '/pages/balance/balance'
    })
  },
})
