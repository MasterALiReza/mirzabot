# 🤖 Ox Panel & OxBot

🇮🇷 **[برای مشاهده راهنمای فارسی، اینجا کلیک کنید (README_FA.md)](README_FA.md)**

---

A powerful, automated system for selling and managing VPN subscriptions directly via a Telegram Bot, backed by a beautiful Web Admin Panel. 

---

## 🔌 Supported Panels & Backends

OxBot provides full integration and automated config creation for:
- 🟢 **Marzban**
- 🟢 **3x-ui** / **X-UI**
- 🟢 **Hiddify**
- 🟢 **Alireza Panel**
- 🟢 **IBSng**
- 🟢 **WG-Dashboard** (WireGuard)
- 🟢 **Mikrotik**

---

## ⚙️ Features

### 🖥️ Web Admin Panel
- **Sales Dashboard**: Track daily/monthly revenue, live users, and active server stats.
- **Server Manager**: Add, update, and manage multiple VPN servers from a single dashboard.
- **Product Management**: Set up custom plans (time/volume limits), pricing, and categories.
- **Payment Gateways**: Configure crypto (NowPayments) and local gateways with ease.
- **Bot Settings**: Personalize bot messages, buttons, and keyboard menus instantly.

### 📱 Telegram Bot
- **Auto-Delivery**: Instantly sends connection configs and QR codes to users after payment.
- **User Services**: Check subscription info, renew plans, purchase extra volume, and get support.
- **Free Trials**: Automated test packages for new users to check connection speed.
- **Anti-Abuse**: Mobile phone verification to block spam and multiple trial accounts.
- **Ticket Support**: Built-in support system linking users directly to the admin panel.

---

## 🚀 Installation & Update

### Prerequisites
- **OS**: Ubuntu Server 22.04+ (Fresh install recommended)
- **Domain**: A domain pointing to your server IP.

### 🔧 Direct Installation (Stable)
Run this command in your root terminal:
```bash
curl -o install.sh -L https://raw.githubusercontent.com/MasterALiReza/OxBot/main/install.sh && bash install.sh
```
*Select option **1** in the menu.*

### 🔄 Updating
Run the same command and select option **2 (Update OxBot)** to update safely without data loss:
```bash
curl -o install.sh -L https://raw.githubusercontent.com/MasterALiReza/OxBot/main/install.sh && bash install.sh
```

---

## 🙏 Credits & Open Source
This project is built upon the open-source community.
- **Original Source**: OxBot is based on the original **[Mirza Panel by mahdiMGF2](https://github.com/mahdiMGF2/mirzabot)** project. We extend our thanks to the original developers and contributors.
