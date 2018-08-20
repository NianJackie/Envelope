//share.js
//获取应用实例
const app = getApp()

Page({
  data: {
    userInfo: {},
    hasUserInfo: false,
    team_id: 0,
    con: '邀您参加“嘴”强王者瓜分现金大奖',
    canIUse: wx.canIUse('button.open-type.getUserInfo'),
    ownerImg: '',
    ownerName: '',
    xcxewm:''
  },

  mytry: function () {
    wx.navigateBack({
      delta: 1
    })
  },

  //转发
  onShareAppMessage: function (res) {
    var title = this.data.ownerName + this.data.con;
    if (res.from === 'button') {
      console.log(res)
    }
    return {
      title: title,
      path: '/pages/index/record/record?team_id='+this.data.team_id,
      success: function (res) {
        wx.showToast({
          title: '转发成功',
          icon: "none",
          duration: 2000
        })
      },
      fail: function (res) {
      }
    }
  },

  //生成朋友圈分享图
  sheng:function(){
    var fximg = this.data.xcxewm;
    wx.previewImage({
      current: '',
      urls: [fximg]
    })
  },

  //获取登录信息
  onLoad: function (option) {
    var that = this;
    var info = app.globalData.userInfo,
        tok = app.globalData.token;

    that.setData({
      team_id: option.team_id,
      userInfo: info,
      token: tok,
      hasUserInfo: true,
      ownerName: info.nickName,
      ownerImg: info.avatarUrl
    })
    
    wx.showLoading({
      title: '二维码生成中',
      mask: true
    })

    var postUrl = app.setConfig.url + '/index.php?g=Api&m=Game&a=get_code',
      postData = {
        token: tok,
        team_id: option.team_id,
        tit: this.data.ownerName,
        con: this.data.con,
        page: 'pages/index/record/record'
      };
    app.postLogin(postUrl, postData, this.setCode);  
  },
  
  setCode: function(res){
    if(res.data.code === 20000){
      var datas = res.data;
      this.setData({
        xcxewm: app.setConfig.url + '/' + datas.data
      })
      wx.showToast({
        title: '生成成功',
        icon: "none",
        duration: 2000
      })
    }
  }
})
