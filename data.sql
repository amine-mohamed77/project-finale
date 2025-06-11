/*==============================================================*/
/* DBMS name:      SAP SQL Anywhere 17                          */
/* Created on:     6/2/2025 10:49:05 AM                         */
/*==============================================================*/


if exists(select 1 from sys.sysforeignkey where role='FK_HOUSE_HOUSE_CIT_CITY') then
    alter table HOUSE
       delete foreign key FK_HOUSE_HOUSE_CIT_CITY
end if;

if exists(select 1 from sys.sysforeignkey where role='FK_HOUSE_HOUSE_PRO_PROPRETY') then
    alter table HOUSE
       delete foreign key FK_HOUSE_HOUSE_PRO_PROPRETY
end if;

if exists(select 1 from sys.sysforeignkey where role='FK_HOUSE_OWNER_HOU_OWNER') then
    alter table HOUSE
       delete foreign key FK_HOUSE_OWNER_HOU_OWNER
end if;

if exists(select 1 from sys.sysforeignkey where role='FK_HOUSE_PR_HOUSE_PRO_PROPRETY') then
    alter table HOUSE_PROPERTY_PICTURES
       delete foreign key FK_HOUSE_PR_HOUSE_PRO_PROPRETY
end if;

if exists(select 1 from sys.sysforeignkey where role='FK_HOUSE_PR_HOUSE_PRO_HOUSE') then
    alter table HOUSE_PROPERTY_PICTURES
       delete foreign key FK_HOUSE_PR_HOUSE_PRO_HOUSE
end if;

if exists(select 1 from sys.sysforeignkey where role='FK_MSG_ONWE_MSG_ONWER_OWNER') then
    alter table MSG_ONWER
       delete foreign key FK_MSG_ONWE_MSG_ONWER_OWNER
end if;

if exists(select 1 from sys.sysforeignkey where role='FK_MSG_ONWE_MSG_ONWER_MESSAGES') then
    alter table MSG_ONWER
       delete foreign key FK_MSG_ONWE_MSG_ONWER_MESSAGES
end if;

if exists(select 1 from sys.sysforeignkey where role='FK_MSG_STUD_MSG_STUDE_STUDENT') then
    alter table MSG_STUDENT
       delete foreign key FK_MSG_STUD_MSG_STUDE_STUDENT
end if;

if exists(select 1 from sys.sysforeignkey where role='FK_MSG_STUD_MSG_STUDE_MESSAGES') then
    alter table MSG_STUDENT
       delete foreign key FK_MSG_STUD_MSG_STUDE_MESSAGES
end if;

if exists(select 1 from sys.sysforeignkey where role='FK_OWNER_ST_OWNER_STU_STUDENT') then
    alter table OWNER_STUDENT
       delete foreign key FK_OWNER_ST_OWNER_STU_STUDENT
end if;

if exists(select 1 from sys.sysforeignkey where role='FK_OWNER_ST_OWNER_STU_OWNER') then
    alter table OWNER_STUDENT
       delete foreign key FK_OWNER_ST_OWNER_STU_OWNER
end if;

if exists(select 1 from sys.sysforeignkey where role='FK_PICTURE_OWNER_PIC_OWNER') then
    alter table PICTURE
       delete foreign key FK_PICTURE_OWNER_PIC_OWNER
end if;

if exists(select 1 from sys.sysforeignkey where role='FK_SEND_NOT_SEND_NOTI_NOTIFICA') then
    alter table SEND_NOTIFICATION
       delete foreign key FK_SEND_NOT_SEND_NOTI_NOTIFICA
end if;

if exists(select 1 from sys.sysforeignkey where role='FK_SEND_NOT_SEND_NOTI_MESSAGES') then
    alter table SEND_NOTIFICATION
       delete foreign key FK_SEND_NOT_SEND_NOTI_MESSAGES
end if;

if exists(select 1 from sys.sysforeignkey where role='FK_STUDENT_PICTURE_S_PICTURE') then
    alter table STUDENT
       delete foreign key FK_STUDENT_PICTURE_S_PICTURE
end if;

if exists(select 1 from sys.sysforeignkey where role='FK_STUDENT__STUDENT_H_HOUSE') then
    alter table STUDENT_HOUSE
       delete foreign key FK_STUDENT__STUDENT_H_HOUSE
end if;

if exists(select 1 from sys.sysforeignkey where role='FK_STUDENT__STUDENT_H_STUDENT') then
    alter table STUDENT_HOUSE
       delete foreign key FK_STUDENT__STUDENT_H_STUDENT
end if;

drop index if exists CITY.CITY_PK;

drop table if exists CITY;

drop index if exists HOUSE.OWNER_HOUSE_FK;

drop index if exists HOUSE.HOUSE_PROPERTY_TYPE_FK;

drop index if exists HOUSE.HOUSE_CITY_FK;

drop index if exists HOUSE.HOUSE_PK;

drop table if exists HOUSE;

drop index if exists HOUSE_PROPERTY_PICTURES.HOUSE_PROPERTY_PICTURES_FK;

drop index if exists HOUSE_PROPERTY_PICTURES.HOUSE_PROPERTY_PICTURES2_FK;

drop index if exists HOUSE_PROPERTY_PICTURES.HOUSE_PROPERTY_PICTURES_PK;

drop table if exists HOUSE_PROPERTY_PICTURES;

drop index if exists MESSAGES.MESSAGES_PK;

drop table if exists MESSAGES;

drop index if exists MSG_ONWER.MSG_ONWER_FK;

drop index if exists MSG_ONWER.MSG_ONWER2_FK;

drop index if exists MSG_ONWER.MSG_ONWER_PK;

drop table if exists MSG_ONWER;

drop index if exists MSG_STUDENT.MSG_STUDENT_FK;

drop index if exists MSG_STUDENT.MSG_STUDENT2_FK;

drop index if exists MSG_STUDENT.MSG_STUDENT_PK;

drop table if exists MSG_STUDENT;

drop index if exists NOTIFICATIONS.NOTIFICATIONS_PK;

drop table if exists NOTIFICATIONS;

drop index if exists OWNER.OWNER_PK;

drop table if exists OWNER;

drop index if exists OWNER_STUDENT.OWNER_STUDENT_FK;

drop index if exists OWNER_STUDENT.OWNER_STUDENT2_FK;

drop index if exists OWNER_STUDENT.OWNER_STUDENT_PK;

drop table if exists OWNER_STUDENT;

drop index if exists PICTURE.OWNER_PICTURE_FK;

drop index if exists PICTURE.PICTURE_PK;

drop table if exists PICTURE;

drop index if exists PROPRETY_PICTURES.PROPRETY_PICTURES_PK;

drop table if exists PROPRETY_PICTURES;

drop index if exists PROPRETY_TYPE.PROPRETY_TYPE_PK;

drop table if exists PROPRETY_TYPE;

drop index if exists SEND_NOTIFICATION.SEND_NOTIFICATION_FK;

drop index if exists SEND_NOTIFICATION.SEND_NOTIFICATION2_FK;

drop index if exists SEND_NOTIFICATION.SEND_NOTIFICATION_PK;

drop table if exists SEND_NOTIFICATION;

drop index if exists STUDENT.PICTURE_STUDENT_FK;

drop index if exists STUDENT.STUDENT_PK;

drop table if exists STUDENT;

drop index if exists STUDENT_HOUSE.STUDENT_HOUSE_FK;

drop index if exists STUDENT_HOUSE.STUDENT_HOUSE2_FK;

drop index if exists STUDENT_HOUSE.STUDENT_HOUSE_PK;

drop table if exists STUDENT_HOUSE;

/*==============================================================*/
/* Table: CITY                                                  */
/*==============================================================*/
create or replace table CITY 
(
   CITY_ID              integer                        not null,
   CITY_NAME            long varchar                   null,
   constraint PK_CITY primary key clustered (CITY_ID)
);

/*==============================================================*/
/* Index: CITY_PK                                               */
/*==============================================================*/
create unique clustered index CITY_PK on CITY (
CITY_ID ASC
);

/*==============================================================*/
/* Table: HOUSE                                                 */
/*==============================================================*/
create or replace table HOUSE 
(
   HOUSE_ID             integer                        not null,
   CITY_ID              integer                        not null,
   PROPRETY_TYPE_ID     integer                        not null,
   OWNER_ID             integer                        not null,
   HOUSE_TITLE          long varchar                   null,
   HOUSE_PRICE          float                          null,
   HOUSE_LOCATION       long varchar                   null,
   HOUSE_BADROOM        integer                        null,
   HOUSE_BATHROOM       integer                        null,
   HOUSE_DESCRIPTION    long varchar                   null,
   constraint PK_HOUSE primary key clustered (HOUSE_ID)
);

/*==============================================================*/
/* Index: HOUSE_PK                                              */
/*==============================================================*/
create unique clustered index HOUSE_PK on HOUSE (
HOUSE_ID ASC
);

/*==============================================================*/
/* Index: HOUSE_CITY_FK                                         */
/*==============================================================*/
create index HOUSE_CITY_FK on HOUSE (
CITY_ID ASC
);

/*==============================================================*/
/* Index: HOUSE_PROPERTY_TYPE_FK                                */
/*==============================================================*/
create index HOUSE_PROPERTY_TYPE_FK on HOUSE (
PROPRETY_TYPE_ID ASC
);

/*==============================================================*/
/* Index: OWNER_HOUSE_FK                                        */
/*==============================================================*/
create index OWNER_HOUSE_FK on HOUSE (
OWNER_ID ASC
);

/*==============================================================*/
/* Table: HOUSE_PROPERTY_PICTURES                               */
/*==============================================================*/
create or replace table HOUSE_PROPERTY_PICTURES 
(
   HOUSE_ID             integer                        not null,
   PROPRETY_PICTURES_ID integer                        not null,
   HOUSE_PROPERTY_PICTURES_DATE timestamp                      null,
   constraint PK_HOUSE_PROPERTY_PICTURES primary key clustered (HOUSE_ID, PROPRETY_PICTURES_ID)
);

/*==============================================================*/
/* Index: HOUSE_PROPERTY_PICTURES_PK                            */
/*==============================================================*/
create unique clustered index HOUSE_PROPERTY_PICTURES_PK on HOUSE_PROPERTY_PICTURES (
HOUSE_ID ASC,
PROPRETY_PICTURES_ID ASC
);

/*==============================================================*/
/* Index: HOUSE_PROPERTY_PICTURES2_FK                           */
/*==============================================================*/
create index HOUSE_PROPERTY_PICTURES2_FK on HOUSE_PROPERTY_PICTURES (
HOUSE_ID ASC
);

/*==============================================================*/
/* Index: HOUSE_PROPERTY_PICTURES_FK                            */
/*==============================================================*/
create index HOUSE_PROPERTY_PICTURES_FK on HOUSE_PROPERTY_PICTURES (
PROPRETY_PICTURES_ID ASC
);

/*==============================================================*/
/* Table: MESSAGES                                              */
/*==============================================================*/
create or replace table MESSAGES 
(
   MESSAGE_ID           integer                        not null,
   SENDER_TYPE          long varchar                   null,
   MESSAGE_TEXT         long varchar                   null,
   MESSAGE_DATE         timestamp                      null,
   IS_READ              smallint                       null,
   IMAGE_URL            varchar(255)            DEFAULT NULL,
   constraint PK_MESSAGES primary key clustered (MESSAGE_ID)
);

/*==============================================================*/
/* Index: MESSAGES_PK                                           */
/*==============================================================*/
create unique clustered index MESSAGES_PK on MESSAGES (
MESSAGE_ID ASC
);

/*==============================================================*/
/* Table: MSG_ONWER                                             */
/*==============================================================*/
create or replace table MSG_ONWER 
(
   MESSAGE_ID           integer                        not null,
   OWNER_ID             integer                        not null,
   MSG_DATE             timestamp                      null,
   constraint PK_MSG_ONWER primary key clustered (MESSAGE_ID, OWNER_ID)
);

/*==============================================================*/
/* Index: MSG_ONWER_PK                                          */
/*==============================================================*/
create unique clustered index MSG_ONWER_PK on MSG_ONWER (
MESSAGE_ID ASC,
OWNER_ID ASC
);

/*==============================================================*/
/* Index: MSG_ONWER2_FK                                         */
/*==============================================================*/
create index MSG_ONWER2_FK on MSG_ONWER (
MESSAGE_ID ASC
);

/*==============================================================*/
/* Index: MSG_ONWER_FK                                          */
/*==============================================================*/
create index MSG_ONWER_FK on MSG_ONWER (
OWNER_ID ASC
);

/*==============================================================*/
/* Table: MSG_STUDENT                                           */
/*==============================================================*/
create or replace table MSG_STUDENT 
(
   MESSAGE_ID           integer                        not null,
   STUDENT_ID           integer                        not null,
   MESSAGE_DATE         timestamp                      null,
   constraint PK_MSG_STUDENT primary key clustered (MESSAGE_ID, STUDENT_ID)
);

/*==============================================================*/
/* Index: MSG_STUDENT_PK                                        */
/*==============================================================*/
create unique clustered index MSG_STUDENT_PK on MSG_STUDENT (
MESSAGE_ID ASC,
STUDENT_ID ASC
);

/*==============================================================*/
/* Index: MSG_STUDENT2_FK                                       */
/*==============================================================*/
create index MSG_STUDENT2_FK on MSG_STUDENT (
MESSAGE_ID ASC
);

/*==============================================================*/
/* Index: MSG_STUDENT_FK                                        */
/*==============================================================*/
create index MSG_STUDENT_FK on MSG_STUDENT (
STUDENT_ID ASC
);

/*==============================================================*/
/* Table: NOTIFICATIONS                                         */
/*==============================================================*/
create or replace table NOTIFICATIONS 
(
   NOTIFICATION_ID      integer                        not null,
   CREATED_AT           timestamp                      null,
   constraint PK_NOTIFICATIONS primary key clustered (NOTIFICATION_ID)
);

/*==============================================================*/
/* Index: NOTIFICATIONS_PK                                      */
/*==============================================================*/
create unique clustered index NOTIFICATIONS_PK on NOTIFICATIONS (
NOTIFICATION_ID ASC
);

/*==============================================================*/
/* Table: OWNER                                                 */
/*==============================================================*/
create or replace table OWNER 
(
   OWNER_ID             integer                        not null,
   OWNER_NAME           long varchar                   null,
   OWNER_EMAIL          long varchar                   null,
   OWNER_PASSWORD       long varchar                   null,
   constraint PK_OWNER primary key clustered (OWNER_ID)
);

/*==============================================================*/
/* Index: OWNER_PK                                              */
/*==============================================================*/
create unique clustered index OWNER_PK on OWNER (
OWNER_ID ASC
);

/*==============================================================*/
/* Table: OWNER_STUDENT                                         */
/*==============================================================*/
create or replace table OWNER_STUDENT 
(
   OWNER_ID             integer                        not null,
   STUDENT_ID           integer                        not null,
   OWNER_STUDENT_DATE   timestamp                      null,
   constraint PK_OWNER_STUDENT primary key clustered (OWNER_ID, STUDENT_ID)
);

/*==============================================================*/
/* Index: OWNER_STUDENT_PK                                      */
/*==============================================================*/
create unique clustered index OWNER_STUDENT_PK on OWNER_STUDENT (
OWNER_ID ASC,
STUDENT_ID ASC
);

/*==============================================================*/
/* Index: OWNER_STUDENT2_FK                                     */
/*==============================================================*/
create index OWNER_STUDENT2_FK on OWNER_STUDENT (
OWNER_ID ASC
);

/*==============================================================*/
/* Index: OWNER_STUDENT_FK                                      */
/*==============================================================*/
create index OWNER_STUDENT_FK on OWNER_STUDENT (
STUDENT_ID ASC
);

/*==============================================================*/
/* Table: PICTURE                                               */
/*==============================================================*/
create or replace table PICTURE 
(
   PICTURE_ID           integer                        not null,
   OWNER_ID             integer                        not null,
   PICTURE_URL          long varchar                   null,
   constraint PK_PICTURE primary key clustered (PICTURE_ID)
);

/*==============================================================*/
/* Index: PICTURE_PK                                            */
/*==============================================================*/
create unique clustered index PICTURE_PK on PICTURE (
PICTURE_ID ASC
);

/*==============================================================*/
/* Index: OWNER_PICTURE_FK                                      */
/*==============================================================*/
create index OWNER_PICTURE_FK on PICTURE (
OWNER_ID ASC
);

/*==============================================================*/
/* Table: PROPRETY_PICTURES                                     */
/*==============================================================*/
create or replace table PROPRETY_PICTURES 
(
   PROPRETY_PICTURES_ID integer                        not null,
   PROPRETY_PICTURES_NAME long varchar                   null,
   constraint PK_PROPRETY_PICTURES primary key clustered (PROPRETY_PICTURES_ID)
);

/*==============================================================*/
/* Index: PROPRETY_PICTURES_PK                                  */
/*==============================================================*/
create unique clustered index PROPRETY_PICTURES_PK on PROPRETY_PICTURES (
PROPRETY_PICTURES_ID ASC
);

/*==============================================================*/
/* Table: PROPRETY_TYPE                                         */
/*==============================================================*/
create or replace table PROPRETY_TYPE 
(
   PROPRETY_TYPE_ID     integer                        not null,
   PROPRETY_TYPE_NAME   long varchar                   null,
   constraint PK_PROPRETY_TYPE primary key clustered (PROPRETY_TYPE_ID)
);

/*==============================================================*/
/* Index: PROPRETY_TYPE_PK                                      */
/*==============================================================*/
create unique clustered index PROPRETY_TYPE_PK on PROPRETY_TYPE (
PROPRETY_TYPE_ID ASC
);

/*==============================================================*/
/* Table: SEND_NOTIFICATION                                     */
/*==============================================================*/
create or replace table SEND_NOTIFICATION 
(
   MESSAGE_ID           integer                        not null,
   NOTIFICATION_ID      integer                        not null,
   USER_TYPE            long varchar                   null,
   NOTIFICATION_TYPE    long varchar                   null,
   "MESSAGE"            long varchar                   null,
   IS_READ              smallint                       null,
   constraint PK_SEND_NOTIFICATION primary key clustered (MESSAGE_ID, NOTIFICATION_ID)
);

/*==============================================================*/
/* Index: SEND_NOTIFICATION_PK                                  */
/*==============================================================*/
create unique clustered index SEND_NOTIFICATION_PK on SEND_NOTIFICATION (
MESSAGE_ID ASC,
NOTIFICATION_ID ASC
);

/*==============================================================*/
/* Index: SEND_NOTIFICATION2_FK                                 */
/*==============================================================*/
create index SEND_NOTIFICATION2_FK on SEND_NOTIFICATION (
MESSAGE_ID ASC
);

/*==============================================================*/
/* Index: SEND_NOTIFICATION_FK                                  */
/*==============================================================*/
create index SEND_NOTIFICATION_FK on SEND_NOTIFICATION (
NOTIFICATION_ID ASC
);

/*==============================================================*/
/* Table: STUDENT                                               */
/*==============================================================*/
create or replace table STUDENT 
(
   STUDENT_ID           integer                        not null,
   PICTURE_ID           integer                        not null,
   STUDENT_NAME         long varchar                   null,
   STUDENT_EMAIL        long varchar                   null,
   STUDENT_PASSWORD     long varchar                   null,
   constraint PK_STUDENT primary key clustered (STUDENT_ID)
);

/*==============================================================*/
/* Index: STUDENT_PK                                            */
/*==============================================================*/
create unique clustered index STUDENT_PK on STUDENT (
STUDENT_ID ASC
);

/*==============================================================*/
/* Index: PICTURE_STUDENT_FK                                    */
/*==============================================================*/
create index PICTURE_STUDENT_FK on STUDENT (
PICTURE_ID ASC
);

/*==============================================================*/
/* Table: STUDENT_HOUSE                                         */
/*==============================================================*/
create or replace table STUDENT_HOUSE 
(
   STUDENT_ID           integer                        not null,
   HOUSE_ID             integer                        not null,
   STUDENT_HOUSE_DATE   date                           null,
   constraint PK_STUDENT_HOUSE primary key clustered (STUDENT_ID, HOUSE_ID)
);

/*==============================================================*/
/* Index: STUDENT_HOUSE_PK                                      */
/*==============================================================*/
create unique clustered index STUDENT_HOUSE_PK on STUDENT_HOUSE (
STUDENT_ID ASC,
HOUSE_ID ASC
);

/*==============================================================*/
/* Index: STUDENT_HOUSE2_FK                                     */
/*==============================================================*/
create index STUDENT_HOUSE2_FK on STUDENT_HOUSE (
STUDENT_ID ASC
);

/*==============================================================*/
/* Index: STUDENT_HOUSE_FK                                      */
/*==============================================================*/
create index STUDENT_HOUSE_FK on STUDENT_HOUSE (
HOUSE_ID ASC
);

alter table HOUSE
   add constraint FK_HOUSE_HOUSE_CIT_CITY foreign key (CITY_ID)
      references CITY (CITY_ID)
      on update restrict
      on delete restrict;

alter table HOUSE
   add constraint FK_HOUSE_HOUSE_PRO_PROPRETY foreign key (PROPRETY_TYPE_ID)
      references PROPRETY_TYPE (PROPRETY_TYPE_ID)
      on update restrict
      on delete restrict;

alter table HOUSE
   add constraint FK_HOUSE_OWNER_HOU_OWNER foreign key (OWNER_ID)
      references OWNER (OWNER_ID)
      on update restrict
      on delete restrict;

alter table HOUSE_PROPERTY_PICTURES
   add constraint FK_HOUSE_PR_HOUSE_PRO_PROPRETY foreign key (PROPRETY_PICTURES_ID)
      references PROPRETY_PICTURES (PROPRETY_PICTURES_ID)
      on update restrict
      on delete restrict;

alter table HOUSE_PROPERTY_PICTURES
   add constraint FK_HOUSE_PR_HOUSE_PRO_HOUSE foreign key (HOUSE_ID)
      references HOUSE (HOUSE_ID)
      on update restrict
      on delete restrict;

alter table MSG_ONWER
   add constraint FK_MSG_ONWE_MSG_ONWER_OWNER foreign key (OWNER_ID)
      references OWNER (OWNER_ID)
      on update restrict
      on delete restrict;

alter table MSG_ONWER
   add constraint FK_MSG_ONWE_MSG_ONWER_MESSAGES foreign key (MESSAGE_ID)
      references MESSAGES (MESSAGE_ID)
      on update restrict
      on delete restrict;

alter table MSG_STUDENT
   add constraint FK_MSG_STUD_MSG_STUDE_STUDENT foreign key (STUDENT_ID)
      references STUDENT (STUDENT_ID)
      on update restrict
      on delete restrict;

alter table MSG_STUDENT
   add constraint FK_MSG_STUD_MSG_STUDE_MESSAGES foreign key (MESSAGE_ID)
      references MESSAGES (MESSAGE_ID)
      on update restrict
      on delete restrict;

alter table OWNER_STUDENT
   add constraint FK_OWNER_ST_OWNER_STU_STUDENT foreign key (STUDENT_ID)
      references STUDENT (STUDENT_ID)
      on update restrict
      on delete restrict;

alter table OWNER_STUDENT
   add constraint FK_OWNER_ST_OWNER_STU_OWNER foreign key (OWNER_ID)
      references OWNER (OWNER_ID)
      on update restrict
      on delete restrict;

alter table PICTURE
   add constraint FK_PICTURE_OWNER_PIC_OWNER foreign key (OWNER_ID)
      references OWNER (OWNER_ID)
      on update restrict
      on delete restrict;

alter table SEND_NOTIFICATION
   add constraint FK_SEND_NOT_SEND_NOTI_NOTIFICA foreign key (NOTIFICATION_ID)
      references NOTIFICATIONS (NOTIFICATION_ID)
      on update restrict
      on delete restrict;

alter table SEND_NOTIFICATION
   add constraint FK_SEND_NOT_SEND_NOTI_MESSAGES foreign key (MESSAGE_ID)
      references MESSAGES (MESSAGE_ID)
      on update restrict
      on delete restrict;

alter table STUDENT
   add constraint FK_STUDENT_PICTURE_S_PICTURE foreign key (PICTURE_ID)
      references PICTURE (PICTURE_ID)
      on update restrict
      on delete restrict;

alter table STUDENT_HOUSE
   add constraint FK_STUDENT__STUDENT_H_HOUSE foreign key (HOUSE_ID)
      references HOUSE (HOUSE_ID)
      on update restrict
      on delete restrict;

alter table STUDENT_HOUSE
   add constraint FK_STUDENT__STUDENT_H_STUDENT foreign key (STUDENT_ID)
      references STUDENT (STUDENT_ID)
      on update restrict
      on delete restrict;

