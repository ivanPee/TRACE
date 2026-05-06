import React from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import FontAwesome5 from 'react-native-vector-icons/FontAwesome5';
import { useAppContext } from '../context/AppContext';
import { shellTabsByRole } from '../navigation/appShellConfig';
import { colors } from '../theme/colors';

const iconByKey = {
  home: 'home',
  students: 'child',
  bookings: 'comment-dots',
  transactions: 'history',
  profile: 'user-circle',
  ride: 'route',
  support: 'life-ring',
};

export default function AppNavBar({ navigation, active, onTabPress }) {
  const { currentRole } = useAppContext();
  const items = shellTabsByRole[currentRole] || shellTabsByRole.parent;

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
              hitSlop={8}
              onPress={() => {
                if (onTabPress) {
                  onTabPress(item.route);
                  return;
                }

                navigation.navigate(item.route);
              }}
              style={({ pressed }) => [styles.item, pressed && styles.pressed]}
            >
              <View style={[styles.iconWrap, isActive && styles.activeIconWrap]}>
                <FontAwesome5
                  name={iconByKey[item.key] || 'circle'}
                  size={isActive ? 16 : 15}
                  solid={isActive}
                  color={isActive ? colors.white : styles.inactiveIcon.color}
                />
              </View>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  shell: {
    backgroundColor: colors.paper,
    paddingTop: 0,
    paddingBottom: 0,
  },
  bar: {
    minHeight: 56,
    backgroundColor: '#1a1d21',
    borderTopWidth: 1,
    borderTopColor: '#25292e',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 6,
    paddingVertical: 4,
    shadowColor: '#000000',
    shadowOpacity: 0.1,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: -2 },
    elevation: 10,
  },
  item: {
    flex: 1,
    minHeight: 44,
    alignItems: 'center',
    justifyContent: 'center',
  },
  pressed: {
    opacity: 0.84,
  },
  iconWrap: {
    width: 34,
    height: 34,
    alignItems: 'center',
    justifyContent: 'center',
  },
  activeIconWrap: {
    width: 36,
    height: 36,
    borderRadius: 11,
    backgroundColor: colors.accent,
  },
  inactiveIcon: {
    color: 'rgba(255,255,255,0.60)',
  },
});
