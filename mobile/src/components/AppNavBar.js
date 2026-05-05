import React from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { useAppContext } from '../context/AppContext';
import { colors } from '../theme/colors';

const routeByRole = {
  parent: [
    { key: 'home', route: 'ParentDashboard', icon: 'home', label: 'Home' },
    { key: 'students', route: 'Students', icon: 'students', label: 'Students' },
    { key: 'bookings', route: 'Bookings', icon: 'bookings', label: 'Bookings', featured: true },
    { key: 'transactions', route: 'Transactions', icon: 'transactions', label: 'Transactions' },
    { key: 'profile', route: 'Profile', icon: 'profile', label: 'Profile' },
  ],
  driver: [
    { key: 'home', route: 'DriverDashboard', icon: 'home', label: 'Home' },
    { key: 'students', route: 'Students', icon: 'students', label: 'Students' },
    { key: 'bookings', route: 'Bookings', icon: 'bookings', label: 'Bookings', featured: true },
    { key: 'transactions', route: 'Transactions', icon: 'transactions', label: 'Transactions' },
    { key: 'profile', route: 'Profile', icon: 'profile', label: 'Profile' },
  ],
  student: [
    { key: 'home', route: 'StudentDashboard', icon: 'home', label: 'Home' },
    { key: 'ride', route: 'ActiveRideMap', icon: 'ride', label: 'Ride', featured: true },
    { key: 'support', route: 'Chat', icon: 'support', label: 'Support' },
  ],
};

export default function AppNavBar({ navigation, active }) {
  const { currentRole } = useAppContext();
  const items = routeByRole[currentRole] || routeByRole.parent;

  return (
    <View style={styles.shell}>
      <View style={styles.bar}>
        {items.map((item) => {
          const isActive = item.key === active;

          return (
            <Pressable
              key={item.key}
              accessibilityRole="button"
              accessibilityLabel={item.label}
              accessibilityState={{ selected: isActive }}
              onPress={() => navigation.navigate(item.route)}
              style={({ pressed }) => [
                styles.item,
                item.featured && styles.featuredItem,
                isActive && styles.activeItem,
                item.featured && isActive && styles.activeFeaturedItem,
                pressed && styles.pressed,
              ]}
            >
              <View style={[styles.iconWrap, item.featured && styles.featuredIconWrap, isActive && styles.activeIconWrap, item.featured && isActive && styles.activeFeaturedIconWrap]}>
                <NavIcon icon={item.icon} active={isActive} featured={item.featured} />
              </View>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

function NavIcon({ icon, active, featured }) {
  const tint = featured ? (active ? colors.white : colors.deep) : active ? colors.accent : colors.slate;

  if (icon === 'home') {
    return (
      <View style={styles.iconBox}>
        <View style={[styles.homeRoof, { borderBottomColor: tint }]} />
        <View style={[styles.homeBody, { borderColor: tint }]}>
          <View style={[styles.homeDoor, { backgroundColor: tint }]} />
        </View>
      </View>
    );
  }

  if (icon === 'students') {
    return (
      <View style={styles.iconBox}>
        <View style={[styles.studentHeadLeft, { borderColor: tint }]} />
        <View style={[styles.studentHeadRight, { borderColor: tint }]} />
        <View style={[styles.studentBodyLeft, { borderColor: tint }]} />
        <View style={[styles.studentBodyRight, { borderColor: tint }]} />
      </View>
    );
  }

  if (icon === 'bookings') {
    return (
      <View style={styles.iconBox}>
        <View style={[styles.bookingCard, { borderColor: tint }]}>
          <View style={[styles.bookingLine, { backgroundColor: tint }]} />
          <View style={[styles.bookingLineShort, { backgroundColor: tint }]} />
        </View>
        <View style={[styles.bookingDot, { backgroundColor: tint }]} />
      </View>
    );
  }

  if (icon === 'transactions') {
    return (
      <View style={styles.iconBox}>
        <View style={[styles.walletBody, { borderColor: tint }]} />
        <View style={[styles.walletLatch, { borderColor: tint }]}>
          <View style={[styles.walletLatchDot, { backgroundColor: tint }]} />
        </View>
      </View>
    );
  }

  if (icon === 'ride') {
    return (
      <View style={styles.iconBox}>
        <View style={[styles.ridePin, { borderColor: tint }]} />
        <View style={[styles.ridePinCore, { backgroundColor: tint }]} />
        <View style={[styles.rideTrail, { backgroundColor: tint }]} />
      </View>
    );
  }

  if (icon === 'support') {
    return (
      <View style={styles.iconBox}>
        <View style={[styles.supportBubble, { borderColor: tint }]} />
        <View style={[styles.supportTail, { borderTopColor: tint }]} />
        <View style={[styles.supportDot, { backgroundColor: tint }]} />
      </View>
    );
  }

  return (
    <View style={styles.iconBox}>
      <View style={[styles.profileHead, { borderColor: tint }]} />
      <View style={[styles.profileBody, { borderColor: tint }]} />
    </View>
  );
}

const styles = StyleSheet.create({
  shell: {
    backgroundColor: colors.paper,
    paddingHorizontal: 18,
    paddingTop: 10,
    paddingBottom: 14,
  },
  bar: {
    minHeight: 74,
    borderRadius: 26,
    backgroundColor: colors.deep,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 8,
    paddingVertical: 6,
    shadowColor: colors.ink,
    shadowOpacity: 0.18,
    shadowRadius: 18,
    shadowOffset: { width: 0, height: 8 },
    elevation: 10,
  },
  item: {
    flex: 1,
    minHeight: 58,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 18,
    paddingHorizontal: 6,
  },
  featuredItem: {
    marginTop: -26,
  },
  pressed: {
    opacity: 0.82,
  },
  iconWrap: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'transparent',
  },
  featuredIconWrap: {
    width: 58,
    height: 58,
    borderRadius: 29,
    backgroundColor: colors.paper,
    borderWidth: 3,
    borderColor: colors.deep,
    shadowColor: colors.ink,
    shadowOpacity: 0.16,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 4 },
    elevation: 6,
  },
  activeItem: {
    backgroundColor: 'rgba(255, 255, 255, 0.08)',
  },
  activeIconWrap: {
    backgroundColor: colors.accentSoft,
  },
  activeFeaturedItem: {
    backgroundColor: 'transparent',
  },
  activeFeaturedIconWrap: {
    backgroundColor: colors.accent,
    borderColor: colors.paper,
  },
  iconBox: {
    width: 26,
    height: 26,
    alignItems: 'center',
    justifyContent: 'center',
  },
  homeRoof: {
    width: 0,
    height: 0,
    borderLeftWidth: 9,
    borderRightWidth: 9,
    borderBottomWidth: 8,
    borderLeftColor: 'transparent',
    borderRightColor: 'transparent',
    marginBottom: 1,
  },
  homeBody: {
    width: 16,
    height: 12,
    borderWidth: 2,
    borderRadius: 3,
    alignItems: 'center',
    justifyContent: 'flex-end',
    paddingBottom: 1,
  },
  homeDoor: {
    width: 4,
    height: 5,
    borderTopLeftRadius: 2,
    borderTopRightRadius: 2,
  },
  studentHeadLeft: {
    position: 'absolute',
    top: 4,
    left: 4,
    width: 8,
    height: 8,
    borderRadius: 4,
    borderWidth: 2,
  },
  studentHeadRight: {
    position: 'absolute',
    top: 4,
    right: 4,
    width: 8,
    height: 8,
    borderRadius: 4,
    borderWidth: 2,
  },
  studentBodyLeft: {
    position: 'absolute',
    bottom: 4,
    left: 3,
    width: 10,
    height: 8,
    borderWidth: 2,
    borderTopLeftRadius: 6,
    borderTopRightRadius: 6,
    borderBottomWidth: 0,
  },
  studentBodyRight: {
    position: 'absolute',
    bottom: 4,
    right: 3,
    width: 10,
    height: 8,
    borderWidth: 2,
    borderTopLeftRadius: 6,
    borderTopRightRadius: 6,
    borderBottomWidth: 0,
  },
  bookingCard: {
    width: 18,
    height: 16,
    borderWidth: 2,
    borderRadius: 4,
    alignItems: 'center',
    justifyContent: 'center',
    paddingTop: 2,
  },
  bookingLine: {
    width: 8,
    height: 2,
    borderRadius: 999,
    marginBottom: 3,
  },
  bookingLineShort: {
    width: 5,
    height: 2,
    borderRadius: 999,
  },
  bookingDot: {
    position: 'absolute',
    right: 2,
    top: 2,
    width: 6,
    height: 6,
    borderRadius: 3,
  },
  walletBody: {
    width: 18,
    height: 13,
    borderWidth: 2,
    borderRadius: 4,
  },
  walletLatch: {
    position: 'absolute',
    right: 1,
    top: 7,
    width: 8,
    height: 6,
    borderWidth: 2,
    borderRadius: 3,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.paper,
  },
  walletLatchDot: {
    width: 2,
    height: 2,
    borderRadius: 1,
  },
  profileHead: {
    width: 10,
    height: 10,
    borderRadius: 5,
    borderWidth: 2,
    marginBottom: 2,
  },
  profileBody: {
    width: 16,
    height: 10,
    borderWidth: 2,
    borderTopLeftRadius: 8,
    borderTopRightRadius: 8,
    borderBottomWidth: 0,
  },
  ridePin: {
    width: 13,
    height: 13,
    borderWidth: 2,
    borderRadius: 7,
  },
  ridePinCore: {
    position: 'absolute',
    top: 9,
    width: 4,
    height: 4,
    borderRadius: 2,
  },
  rideTrail: {
    position: 'absolute',
    bottom: 1,
    width: 3,
    height: 8,
    borderRadius: 999,
  },
  supportBubble: {
    width: 18,
    height: 13,
    borderWidth: 2,
    borderRadius: 7,
  },
  supportTail: {
    position: 'absolute',
    bottom: 3,
    left: 5,
    width: 0,
    height: 0,
    borderLeftWidth: 4,
    borderRightWidth: 0,
    borderTopWidth: 5,
    borderLeftColor: 'transparent',
    borderRightColor: 'transparent',
  },
  supportDot: {
    position: 'absolute',
    width: 3,
    height: 3,
    borderRadius: 2,
  },
});
