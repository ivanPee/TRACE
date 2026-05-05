import React from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import MaterialCommunityIcons from 'react-native-vector-icons/MaterialCommunityIcons';
import { useAppContext } from '../context/AppContext';
import { shellTabsByRole } from '../navigation/appShellConfig';
import { colors } from '../theme/colors';

const iconByKey = {
  home: 'home-outline',
  students: 'account-group-outline',
  bookings: 'message-text-outline',
  transactions: 'wallet-outline',
  profile: 'account-outline',
  ride: 'map-marker-path',
  support: 'lifebuoy',
};

export default function AppNavBar({ navigation, active, onTabPress }) {
  const { currentRole } = useAppContext();
  const items = shellTabsByRole[currentRole] || shellTabsByRole.parent;

  return (
    <View style={styles.shell}>
      <View style={[styles.bar, items.length <= 3 && styles.compactBar]}>
        {items.map((item) => {
          const isActive = item.key === active;

          return (
            <Pressable
              key={item.key}
              accessibilityRole="button"
              accessibilityLabel={item.label}
              accessibilityState={{ selected: isActive }}
              hitSlop={10}
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
                <MaterialCommunityIcons
                  name={iconByKey[item.key] || 'circle-outline'}
                  size={isActive ? 22 : 21}
                  color={isActive ? colors.white : 'rgba(255,255,255,0.58)'}
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
    paddingHorizontal: 18,
    paddingTop: 4,
    paddingBottom: 10,
  },
  bar: {
    alignSelf: 'center',
    width: '100%',
    maxWidth: 360,
    minHeight: 68,
    borderRadius: 22,
    backgroundColor: '#202327',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 10,
    paddingVertical: 8,
    shadowColor: '#000000',
    shadowOpacity: 0.2,
    shadowRadius: 18,
    shadowOffset: { width: 0, height: 10 },
    elevation: 12,
  },
  compactBar: {
    maxWidth: 280,
    paddingHorizontal: 18,
  },
  item: {
    flex: 1,
    minHeight: 48,
    alignItems: 'center',
    justifyContent: 'center',
  },
  pressed: {
    opacity: 0.82,
  },
  iconWrap: {
    width: 42,
    height: 42,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
  },
  activeIconWrap: {
    width: 50,
    height: 50,
    borderRadius: 16,
    backgroundColor: colors.accent,
    shadowColor: colors.accent,
    shadowOpacity: 0.28,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 5 },
    elevation: 8,
  },
});
