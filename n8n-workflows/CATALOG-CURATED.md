# 📚 n8n Automation Catalog — ฉบับคัดกรองสำหรับ BabyKawaii

> ที่มา: ไฟล์ **"1,900+ n8n Automations by CC Ai Flow.xlsx"** (รวม 1,989 workflow)  
> คัดเฉพาะหมวดที่เกี่ยวกับร้านค้าออนไลน์ + โซเชียล มาแมพกับฟีเจอร์ที่มีอยู่จริงในระบบ

## ⚠️ ข้อควรระวัง (ตรวจสอบแล้วด้วยการสุ่มโหลด 6 ไฟล์)
ชื่อภาษาไทยในแคตตาล็อกถูก AI แปล/แปะ จึง **ตรงกับเนื้อหาไฟล์จริงราว ๆ 50%** เท่านั้น
เช่น ป้าย "Auto-Generate Product Descriptions" แต่ไฟล์จริงเป็น *Weather Fetcher*,
ป้าย "ระบบแจ้งเตือน Inventory" แต่ไฟล์จริงเป็น *WordPress Content Creator*.
**→ ก่อน import ทุกครั้ง ให้เปิดดู node ในไฟล์ JSON ว่าตรงกับที่ต้องการจริงไหม**

## ✅ ตัวที่ผมตรวจแล้วว่าใช้ได้จริง และดึงเข้าโปรเจคให้แล้ว
| Workflow | สถานะ | ไฟล์ในโปรเจค |
|---|---|---|
| Automated Social Media Content Publishing Factory (100 nodes) | ✅ ตรงไฟล์ (เก็บเป็น reference) | `n8n-workflows/reference/social-publishing-factory-100nodes.json` |
| → ดึง pattern "AI เขียน caption" มาต่อยอด autopublish เดิม | ✅ สร้างใหม่ พร้อมใช้ | `n8n-workflows/06-calendar-ai-autopublish.json` |
| n8n Error Report to Line (5 nodes) | ✅ ตรงไฟล์ → ดัดแปลงเป็นของร้าน | `n8n-workflows/07-n8n-error-alert.json` |

---

## รายการคัดกรองตามหมวด (พร้อมลิงก์ดาวน์โหลด)

### 📱 โพสต์โซเชียลอัตโนมัติ (เชื่อมกับ ปฏิทินโพสต์ / calendar-autopublish)
_15 ตัวที่เกี่ยวข้องที่สุด_

- **ระบบตีพิมพ์เนื้อหาสื่อสังคมอัตโนมัติ** — [ดาวน์โหลด](https://drive.google.com/uc?id=16TiWLWpgmKYgpIowRCyqkmtodP_EXQEt&export=download)
- **ระบบเผยแพร่เนื้อหาสื่อสังคมและสร้างข้อความอัตโนมัติ** — [ดาวน์โหลด](https://drive.google.com/uc?id=1MBk6NZTWHTiWcEeRcmvEQWPUqEWWs9Ql&export=download)
- **ตัวสร้างวิดีโอด้วย AI และระบบเผยแพร่อัตโนมัติ** — [ดาวน์โหลด](https://drive.google.com/uc?id=1ZG1r4Yn-JESbSeFm2nnBzqKCViA1z0-0&export=download)
- **ระบบสร้างบทความจาก URL อัตโนมัติ** — [ดาวน์โหลด](https://drive.google.com/uc?id=1KvtEsx3VBA7AxrKcIseBdLg285RN6LlY&export=download)
- **Notion โพสต์ประจำวันไปยัง LinkedIn** — [ดาวน์โหลด](https://drive.google.com/uc?id=1O2n1GUgwBpqo38_bK50MpcgivEAYzKXX&export=download)
- **ซิงค์ Content Calendar โดยอัตโนมัติ** — [ดาวน์โหลด](https://drive.google.com/uc?id=11FiGkvLnAbOT_DvxGtL7aDT3Gnl8YTxO&export=download)
- **ตัวสร้าง Slack Standup Report** — [ดาวน์โหลด](https://drive.google.com/uc?id=1NM6kT_a39lNcO-rxQEvGb5OrNDcHOnLN&export=download)
- **การจัดตารางเวลาโพสต์โซเชียลมีเดียโดยอัตโนมัติ** — [ดาวน์โหลด](https://drive.google.com/uc?id=1f677XhgsIw7q4nbCwt14Wc7dsB6_8NTw&export=download)
- **ตัวเขียนและสำนักพิมพ์บล็อกอัตโนมัติ** — [ดาวน์โหลด](https://drive.google.com/uc?id=1J9HfUcq_FexURWb1VDaGAebYz1i182S0&export=download)
- **ตัวเผยแพร่ Blog Post โดยอัตโนมัติ** — [ดาวน์โหลด](https://drive.google.com/uc?id=12tNo6x4LUiL3p9jAt4kNBeuntSdgPBB0&export=download)
- **เครื่องมือสร้างบทความพื้นฐานความรู้ด้วย AI** — [ดาวน์โหลด](https://drive.google.com/uc?id=1iNg8coVYW8esf2ujmFzPaTxtDL4TX6FI&export=download)
- **ผู้สำหรับจัดเตียง WordPress ที่ขับเคลื่อนด้วย AI** — [ดาวน์โหลด](https://drive.google.com/uc?id=1d-_xha3nSuXSKxG2wHjLenHlCU4RDzd2&export=download)
- **ข้อมูลเชิงลึก Social Media อัตโนมัติสำหรับการประชุม** — [ดาวน์โหลด](https://drive.google.com/uc?id=1MJktp6L-76mk2Mm2d5NA3ceKPzpHUNyU&export=download)
- **ตัวสร้างบทความบล็อกแบบอัตโนมัติ** — [ดาวน์โหลด](https://drive.google.com/uc?id=12OwRXh7ZVxKufRJFxmpYkbO3ELeFLX6Y&export=download)
- **ตัวจัดการคำติชมลูกค้า** — [ดาวน์โหลด](https://drive.google.com/uc?id=1RkWqmo9wVd7h-EWwiyXP-gHst2VUhA9H&export=download)

### 💬 แชทบอท / ตอบลูกค้า (เชื่อมกับ Inbox / 04-fb-ig-chatbot)
_15 ตัวที่เกี่ยวข้องที่สุด_

- **ผู้ช่วย Telegram ที่ขับเคลื่อนด้วย AI พร้อมอีเมล ปฏิทิน และจัดการงาน** — [ดาวน์โหลด](https://drive.google.com/uc?id=18qEcxDEtzTsbBsM-MW0cOkUCWa7xDg2p&export=download)
- **ตัวสร้างรายงานวิจัยอัตโนมัติด้วย AI** — [ดาวน์โหลด](https://drive.google.com/uc?id=1VYm-oGmJsy8h0v03qPUP_5hDuuNPq-xT&export=download)
- **ระบบ WhatsApp AI ที่ขับเคลื่อนด้วย OpenAI** — [ดาวน์โหลด](https://drive.google.com/uc?export=download&id=1sS2QGNeGPjVSzVW6brK-qO0HRd50LppC)
- **ระบบจัดการอีเมล Gmail ด้วย AI** — [ดาวน์โหลด](https://drive.google.com/uc?id=1bmZPzyL3dP4a_Q6Ty5bFFFcbNBr3Zl-Y&export=download)
- **สนทนา Multi-Agent AI Chat** — [ดาวน์โหลด](https://drive.google.com/uc?id=1StsXhChjyqinxrQxIlaBt-nwF0tML4WE&export=download)
- **GitHub Issues ไปยัง Telegram Notifications** — [ดาวน์โหลด](https://drive.google.com/uc?id=10CmQTlq5TjaOMW9-IwTufh48MKqdUIBB&export=download)
- **จัดการคิว WhatsApp Customer Support** — [ดาวน์โหลด](https://drive.google.com/uc?id=1np8cbnaevrHRJHLYVvor2v-bUBa6vL5O&export=download)
- **Discord Bot Command Handler ด้วย OpenAI** — [ดาวน์โหลด](https://drive.google.com/uc?id=1nwjx1dTKBGqfxYZvsqVJWgzmEWkNkgB_&export=download)
- **บอต Crypto News Sentiment Telegram** — [ดาวน์โหลด](https://drive.google.com/uc?id=1xkcqA8ETk03QpUoB6C08kXHDq-D8RZEx&export=download)
- **อัปเดตสถานะ Lead ของ CRM โดยอัตโนมัติจากอีเมล** — [ดาวน์โหลด](https://drive.google.com/uc?id=1_RCGMG7mgiyjsKO8Dl5qoXQnHGfNKaOm&export=download)
- **Workflow อนุมัติหลายขั้นตอน** — [ดาวน์โหลด](https://drive.google.com/uc?id=1KAvlVnQvzTDXJtp6Qo5QJE9QawtHp9B_&export=download)
- **ตัวจัดลำดับความสำคัญใหม่ของตั๋วสนับสนุนลูกค้า** — [ดาวน์โหลด](https://drive.google.com/uc?id=1Myl6TX7WGe1dnbzM5vJuG6Am-Z63nEOf&export=download)
- **ผู้ช่วยอีเมลที่ใช้ AI และตัวแนะนำ** — [ดาวน์โหลด](https://drive.google.com/uc?id=1vtj_396VAyhkpgn-2M5WqnnMtBdfZuER&export=download)
- **Router Intent ของ Chatbot แบบ Real-Time** — [ดาวน์โหลด](https://drive.google.com/uc?id=1JZERymjPPLTvjDtRJLF0lEYv0GDURFob&export=download)
- **การกระจายสำรวจและวิเคราะห์โดยอัตโนมัติ** — [ดาวน์โหลด](https://drive.google.com/uc?id=1Ekh91Tt_46nYgbDHhElGUQ1v4quXk7BQ&export=download)

### 🟢 LINE
_15 ตัวที่เกี่ยวข้องที่สุด_

- **ตัวจัดการไปป์ไลน์การรับสมัครโดยอัตโนมัติ** — [ดาวน์โหลด](https://drive.google.com/uc?id=1wajFsS9mSlwQAdIdFIK5yFk7Kwb_QJOe&export=download)
- **ไปป์ไลน์เวกเตอร์การฝังอีเมล Gmail** — [ดาวน์โหลด](https://drive.google.com/uc?id=1WACVJArY_wgc4O21VNY_omQpkKNa7ESK&export=download)
- **ตัวสร้างหมายเหตุเอกสารที่ขับเคลื่อนด้วย AI** — [ดาวน์โหลด](https://drive.google.com/uc?id=1gzUkaTZt7KVHO7kIGsximZeU0igK3W-0&export=download)
- **ตัวปรับเนื้อหาออนไลน์** — [ดาวน์โหลด](https://drive.google.com/uc?id=1z5R6nRBuRjp2E6fDkzXSKOldSYEXm3rl&export=download)
- **ตัวสร้างการสนับสนุนลูกค้าแบบออนไลน์** — [ดาวน์โหลด](https://drive.google.com/uc?id=1kAUIUMoCMbkNtLQWBxTqGNb3hD8eoPEy&export=download)
- **ตัวคำนวณคะแนน Slack** — [ดาวน์โหลด](https://drive.google.com/uc?id=1zjI9fJA27gVKAEQi_UDb95GGQXqEB1PS&export=download)
- **Auto-Generate Product Descriptions ด้วย AI** — [ดาวน์โหลด](https://drive.google.com/uc?id=1RBZ1IBVl980DXTaRAwOPuB1W1LDoIfwI&export=download)
- **n8n ตัวแจ้งเตือนข้อผิดพลาดผ่าน Line** — [ดาวน์โหลด](https://drive.google.com/uc?id=1w8jvDLGNxLynXeIf-2AFmqckPoctgcTZ&export=download)
- **Line AI บอทแชท with Groq & Llama3** — [ดาวน์โหลด](https://drive.google.com/uc?id=1r_LfbJDGizARQfpiIUr3Pknwy4JFlAYC&export=download)
- **LINE บอทแชท with Google Calendar & Gmail ai** — [ดาวน์โหลด](https://drive.google.com/uc?id=1R1LdgVCTIR9QnPwqGuzG1Hf8HyGFZoBb&export=download)
- **Thai Line บอทแชท with Google Sheets Memory** — [ดาวน์โหลด](https://drive.google.com/uc?id=1SCC1ObMXfK-eBiFB60Epq3oJZVj0WvkJ&export=download)
- **Line MiniBear Bot: Namecard และระบบอัตโนมัติงาน** — [ดาวน์โหลด](https://drive.google.com/uc?id=1drfLWWKr5iG_kf0Sd_0FSO-QOLfWs9hX&export=download)
- **ระบบอัตโนมัติ: Line Bot Chinese Translator** — [ดาวน์โหลด](https://drive.google.com/uc?id=1uoXt-7ThGEtyCDSmbBIl9hvKOIM6Hkeo&export=download)
- **LINE ไปยัง Google Drive File Upload และตัวบันทึก** — [ดาวน์โหลด](https://drive.google.com/uc?id=1Iv7i0TwPi9qvtE6ggZ6EgoWzN8ZnN0Nh&export=download)
- **ระบบอัตโนมัติ: AI EmAIl Summarizer & Messenger Notifier** — [ดาวน์โหลด](https://drive.google.com/uc?id=1z0xLWSnae_2WyblhN5K524sfC7POXb9r&export=download)

### 🛒 ออเดอร์ / สต็อก / อีคอมเมิร์ซ (เชื่อมกับ orders / stock / 05-tiktok-order-sync)
_15 ตัวที่เกี่ยวข้องที่สุด_

- **Stripe Invoice Payment ไปยัง HubSpot และ Slack Alert** — [ดาวน์โหลด](https://drive.google.com/uc?id=1-xV1B1SRGh9I9_Lnfipv2MsQqn9UCEI4&export=download)
- **ซิงค์ผลิตภัณฑ์ Stripe ไปยัง Shopify** — [ดาวน์โหลด](https://drive.google.com/uc?id=13t8wyDkSFgzB3-vIUsExHqj9nrH7HUag&export=download)
- **สร้างใบแจ้งหนี้โดยอัตโนมัติจากข้อมูลการขาย** — [ดาวน์โหลด](https://drive.google.com/uc?id=1a5-r-_ME1LmcK5Jqh0MDE0j8GlJOvVFw&export=download)
- **PDF Invoice Parser ด้วย OCR** — [ดาวน์โหลด](https://drive.google.com/uc?id=13RxfuYPq89Tvw6vBzOlnLbEPDXevzTN3&export=download)
- **ศูนย์ซิงค์ข้อมูล Real-Time** — [ดาวน์โหลด](https://drive.google.com/uc?id=1r0Xmq3oWC41F7Fuv8ONIKyS-acdMN7Lz&export=download)
- **ระบบการแจ้งเตือนระดับ Inventory** — [ดาวน์โหลด](https://drive.google.com/uc?id=10d7GtGbMu4My8vqlvj0MN130jWaWhIDP&export=download)
- **ตัวสร้างใบเสร็จรับเงินการบริจาคโดยอัตโนมัติ** — [ดาวน์โหลด](https://drive.google.com/uc?id=17AUdIDqimcZM0kf-4CwudACihwm-W02V&export=download)
- **Router สั่งอาหารจัดส่ง Real-Time** — [ดาวน์โหลด](https://drive.google.com/uc?id=1wFCFcdx-uxG_89SlQyICT3j1lyqD2J1a&export=download)
- **ตัวประสานสต็อก Inventory แบบ Real-Time** — [ดาวน์โหลด](https://drive.google.com/uc?id=1SIadsitQIGbMVakO636LO2cvn4m7KOYj&export=download)
- **เครื่องจักรกำหนดราคาแบบไดนามิก Real-Time** — [ดาวน์โหลด](https://drive.google.com/uc?id=14J9HIoAueiaB-P3NNWG8-skUrfh-HaqO&export=download)
- **ตัววางแผนและพยากรณ์ Inventory Real-Time** — [ดาวน์โหลด](https://drive.google.com/uc?id=1ThTO-7vGKAvkACosF3Zi-YtqFSWY_6Q5&export=download)
- **วิธีการนำเข้าผลิตภัณฑ์ WooCommerce และ SEO ที่ขับเคลื่อนด้วย AI** — [ดาวน์โหลด](https://drive.google.com/uc?id=1uVjP6EgDESK46BIPxy75_kkZr_HuYdt-&export=download)
- **แชทบอท WooCommerce Order Support** — [ดาวน์โหลด](https://drive.google.com/uc?id=19235ir2pzc7vQ2F8C4m-7vAH2fy2vj-s&export=download)
- **ตัวจัดการสินค้าคงคลังอัจฉริยะ** — [ดาวน์โหลด](https://drive.google.com/uc?id=1UmruAMbAE91zCEWent3YJrIkuXddPqIz&export=download)
- **ตัวสร้างรายงานการขาย** — [ดาวน์โหลด](https://drive.google.com/uc?id=1ZXIMPZ_ibiKMLqWNKkahR3Nn4XQhUnKi&export=download)

### ⭐ รีวิว / วิเคราะห์ความรู้สึกลูกค้า (กลบช่องว่างที่ตรวจเจอ)
_15 ตัวที่เกี่ยวข้องที่สุด_

- **รวมการกล่าวถึง Twitter ด้วยการวิเคราะห์ Sentiment** — [ดาวน์โหลด](https://drive.google.com/uc?id=11ddK3fUJvBYtFJG8xJpusNXr8q0BHQ-g&export=download)
- **บอต Crypto News Sentiment Telegram** — [ดาวน์โหลด](https://drive.google.com/uc?id=1xkcqA8ETk03QpUoB6C08kXHDq-D8RZEx&export=download)
- **ตัวจัดการวงจร Performance Review โดยอัตโนมัติ** — [ดาวน์โหลด](https://drive.google.com/uc?id=1VeysFkMw9fuzC5Rhk57fzFzysCc55m7i&export=download)
- **Pipeline จากข้อเสนอแนะไปยังการขอคุณสมบัติ Real-Time** — [ดาวน์โหลด](https://drive.google.com/uc?id=1unDHYP_Ky_SBH4UF2IBLJaC1GhPEfPqK&export=download)
- **ตัววิเคราะห์ความรู้สึกของลูกค้า Real-Time** — [ดาวน์โหลด](https://drive.google.com/uc?id=1Edzp1sRA_xiog451YPVldsJeIW4cbJAx&export=download)
- **ตัวติดตามและตรวจสอบชื่อเสียงแบรนด์ Real-Time** — [ดาวน์โหลด](https://drive.google.com/uc?id=17UgJhjKlcvbcH3jUD1xJNDfHeFSoXNnt&export=download)
- **Google Maps Data Scraper ด้วย SERPAPI และ Sheets** — [ดาวน์โหลด](https://drive.google.com/uc?id=1wphljFsb0eh9fwS1wCWzMzHM04KBbKuY&export=download)
- **ตัวสร้างรายงานวิจัยคู่แข่ง AI** — [ดาวน์โหลด](https://drive.google.com/uc?id=1YjeBQIzi4t8o8uZM16_bSN6Ai7CXef3R&export=download)
- **ตัวสร้างข้อมูลเชิงลึกจากการสำรวจ** — [ดาวน์โหลด](https://drive.google.com/uc?id=1MOJ9Tm3WSMLE9rlchKbJ1utqbSCp-OGj&export=download)
- **ตัวสร้างข้อมูลเชิงลึกจากรีวิว Trustpilot** — [ดาวน์โหลด](https://drive.google.com/uc?id=1n2adPiK4d-F-eHFx2rg_QRFVzmWEJ482&export=download)
- **ตัวจัดการประเมินประสิทธิการทำงาน** — [ดาวน์โหลด](https://drive.google.com/uc?id=1eXDuIDkFTwQBemVYNwCo_PkvNU4rLGXu&export=download)
- **ตัวตรวจการเบิกประเมินผล** — [ดาวน์โหลด](https://drive.google.com/uc?id=1mhLTjlfJrjQPhAPj1VgGVQ6nTKtApQT4&export=download)
- **ตัวแสดงการวิเคราะห์ความรู้สึก** — [ดาวน์โหลด](https://drive.google.com/uc?id=1oMXrr19iLkQvNhKsyF4KKBIlwc2GF14g&export=download)
- **การจัดกลุ่ม Slack Messages ตามประเภท** — [ดาวน์โหลด](https://drive.google.com/uc?id=1uiQGPttHyAkardTHXlq5GjdL5rJCHPwb&export=download)
- **การสร้าง Customer Satisfaction Surveys** — [ดาวน์โหลด](https://drive.google.com/uc?id=1VeN3LV0ULPQZulOVDzVc9z77UwBLoL5D&export=download)

### 📊 รายงาน / สรุปยอด → Google Sheets (เชื่อมกับ 02-daily-sales-report)
_15 ตัวที่เกี่ยวข้องที่สุด_

- **ระบบแปลงและลงข้อมูล PDF อัตโนมัติ** — [ดาวน์โหลด](https://drive.google.com/uc?id=1RpBtDSszW_HZeEr0Jw59mppD9QQmcdo4&export=download)
- **ระบบจัดการฝ่ายขายด้วย AI** — [ดาวน์โหลด](https://drive.google.com/uc?id=1Ha00XIJl0y7kKY4exg76IYCSLD5he8MQ&export=download)
- **ChatGPT สร้างรูปภาพไปยัง Google Drive และ Google Sheets** — [ดาวน์โหลด](https://drive.google.com/uc?id=193BeFTZAt4itpj2yRauE-rRpHFSgn7Az&export=download)
- **สร้างรายงานรายสัปดาห์โดยอัตโนมัติจาก Airtable เป็น PDF และ Email** — [ดาวน์โหลด](https://drive.google.com/uc?id=1spthoCrWpDQZNfmkscRFP5oLl6Ztqm1d&export=download)
- **Google Sheets Form Submission ไปยัง Database** — [ดาวน์โหลด](https://drive.google.com/uc?id=1dRomIG37C7cNpt61GmB7WegcYM911_FI&export=download)
- **สร้างใบแจ้งหนี้โดยอัตโนมัติจากข้อมูลการขาย** — [ดาวน์โหลด](https://drive.google.com/uc?id=1a5-r-_ME1LmcK5Jqh0MDE0j8GlJOvVFw&export=download)
- **รวมการกล่าวถึง Twitter ด้วยการวิเคราะห์ Sentiment** — [ดาวน์โหลด](https://drive.google.com/uc?id=11ddK3fUJvBYtFJG8xJpusNXr8q0BHQ-g&export=download)
- **ตรวจสอบและทำความสะอาดข้อมูล Google Sheets** — [ดาวน์โหลด](https://drive.google.com/uc?id=1d3TN6hD_nu48KF9ql53FYRcmUyUhkXMW&export=download)
- **Logger แบบฟอร์มเว็บไซต์ไปยัง Spreadsheet** — [ดาวน์โหลด](https://drive.google.com/uc?id=1NIEcto6N232VrZoEN31Bdy7KvgKvvMRA&export=download)
- **ตัวเขียนและสำนักพิมพ์บล็อกอัตโนมัติ** — [ดาวน์โหลด](https://drive.google.com/uc?id=1J9HfUcq_FexURWb1VDaGAebYz1i182S0&export=download)
- **ระบบติดตามหัวข้อและการสรุปด้วย AI** — [ดาวน์โหลด](https://drive.google.com/uc?id=1gEqXmHx9VT8yXB-RHkpAMMEMq9HqGPN2&export=download)
- **วิธีการนำเข้าผลิตภัณฑ์ WooCommerce และ SEO ที่ขับเคลื่อนด้วย AI** — [ดาวน์โหลด](https://drive.google.com/uc?id=1uVjP6EgDESK46BIPxy75_kkZr_HuYdt-&export=download)
- **ผู้สำหรับจัดเตียง WordPress ที่ขับเคลื่อนด้วย AI** — [ดาวน์โหลด](https://drive.google.com/uc?id=1d-_xha3nSuXSKxG2wHjLenHlCU4RDzd2&export=download)
- **Google Maps Data Scraper ด้วย SERPAPI และ Sheets** — [ดาวน์โหลด](https://drive.google.com/uc?id=1wphljFsb0eh9fwS1wCWzMzHM04KBbKuY&export=download)
- **เวิร์กโฟลว์สมบูรณ์ข้อมูล บริษัท ที่ขับเคลื่อนด้วย AI** — [ดาวน์โหลด](https://drive.google.com/uc?id=11WrRsWD8eZjCRB2VtB3gm8Gn8cHel2PQ&export=download)

### 🖼️ สร้างรูป/คอนเทนต์ด้วย AI (เชื่อมกับ คลังรูปสินค้า / คำอธิบายสินค้า)
_15 ตัวที่เกี่ยวข้องที่สุด_

- **ระบบตีพิมพ์เนื้อหาสื่อสังคมอัตโนมัติ** — [ดาวน์โหลด](https://drive.google.com/uc?id=16TiWLWpgmKYgpIowRCyqkmtodP_EXQEt&export=download)
- **ระบบเผยแพร่เนื้อหาสื่อสังคมและสร้างข้อความอัตโนมัติ** — [ดาวน์โหลด](https://drive.google.com/uc?id=1MBk6NZTWHTiWcEeRcmvEQWPUqEWWs9Ql&export=download)
- **ChatGPT สร้างรูปภาพไปยัง Google Drive และ Google Sheets** — [ดาวน์โหลด](https://drive.google.com/uc?id=193BeFTZAt4itpj2yRauE-rRpHFSgn7Az&export=download)
- **ผู้สร้างโพสต์ WordPress ด้วย AI** — [ดาวน์โหลด](https://drive.google.com/uc?id=1G7i7ecafxywbU9VGgH_ltH9i9tD7woyN&export=download)
- **เครื่องมือสร้างแบนเนอร์เหตุการณ์ AI พร้อม BannerBear** — [ดาวน์โหลด](https://drive.google.com/uc?id=1V87WWASaNoKPBNrB2qJpP5TQQGFZqqax&export=download)
- **ตัวสร้างบทความบล็อกแบบอัตโนมัติ** — [ดาวน์โหลด](https://drive.google.com/uc?id=12OwRXh7ZVxKufRJFxmpYkbO3ELeFLX6Y&export=download)
- **การเลือกและการแสดงภาพ CFP Submission** — [ดาวน์โหลด](https://drive.google.com/uc?id=1y7zlWb9bnZD1jE34rsvxx3tmq6w7mCvS&export=download)
- **ตัวสร้างภาพเบี้ยน AI Telegram** — [ดาวน์โหลด](https://drive.google.com/uc?id=13ldRzBZts2WoUz_PGvgRm5LA8aQEzz1J&export=download)
- **Auto-Generate Product Descriptions ด้วย AI** — [ดาวน์โหลด](https://drive.google.com/uc?id=1RBZ1IBVl980DXTaRAwOPuB1W1LDoIfwI&export=download)
- **Telegram AI Chatbot ด้วย LangChain & Dall-E** — [ดาวน์โหลด](https://drive.google.com/uc?id=1NT_CMlK4uflQ71PZUG1neX-0w1zTvVlx&export=download)
- **ระบบอัตโนมัติ: AI Children’s Storytelling on Telegram** — [ดาวน์โหลด](https://drive.google.com/uc?id=16rM0wWEr_8laC2fdWAkHnK1omWIPdqI7&export=download)
- **อัตโนมัติ Arabic Kids Story ตัวสร้าง** — [ดาวน์โหลด](https://drive.google.com/uc?id=1UwZ7iO77W9fiXd6IbF-VgkKlk0sXt6zk&export=download)
- **เครื่องสร้างรูปภาพแบบ AI โดยอิงตามสไตล์** — [ดาวน์โหลด](https://drive.google.com/uc?id=1_hhrCRRLl-mfJfeBy3jdW7Cg4vuHWKlY&export=download)
- **ระบบอัตโนมัติการปรับปรุงภาพสินค้าและการจัดเก็บ** — [ดาวน์โหลด](https://drive.google.com/uc?id=1A45X4rCf3ty80CpwBveTZNUPd9svA5P3&export=download)
- **ตัวสร้าง AI Style Transfer Image** — [ดาวน์โหลด](https://drive.google.com/uc?id=1RQmtm0JRr2oq4RJhcKx0IvGv-ZCI1UOf&export=download)

---
## วิธีนำไปใช้
1. ดาวน์โหลด JSON จากลิงก์ → เปิดดู node ก่อนว่าตรงความต้องการ
2. n8n → **Import from File** → วาง credential ของคุณ
3. แทนที่ค่า placeholder ให้ตรงกับร้าน (LINE token, API key, Google Sheet ID ฯลฯ)
4. ทดสอบด้วยข้อมูลจริง 1 รายการก่อนเปิด Active

_อัปเดตอัตโนมัติจากสคริปต์คัดกรอง — แก้คำค้นได้ในไฟล์ที่สร้าง index นี้_