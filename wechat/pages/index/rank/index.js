const app = getApp();
Page({
  data: {
    team_id: 0,
    token: '',
    userInfo: {},
    game: {},
    hasUserInfo: false,
    list: [],
    canIUse: wx.canIUse('button.open-type.getUserInfo'),
  },
  onShow: function (option) {
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
  loaddatal: function () {
    wx.showLoading({
      title: '加载中•••'
    })
    var tok = this.data.token;
    var postUrl = app.setConfig.url + '/index.php?g=Api&m=Game&a=rank',
      postData = {
        token: tok
      };
    app.postLogin(postUrl, postData, this.initial);
  },
  // 发出的
  initial: function (res) {
    if (res.data.code == 20000) {
      wx.hideLoading()
      res.data.data.teams.map(i => {
        i.score = (i.score / 1000).toFixed(2)
      })
      this.setData({
        list: res.data.data.teams
      })
    }
  },
  toTeam: function (e) {
    wx.navigateTo({
      url: '/pages/index/game/index?team_id=' + e.currentTarget.dataset.id
    })
  }
})
